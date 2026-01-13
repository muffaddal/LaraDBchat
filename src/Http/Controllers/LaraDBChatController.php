<?php

namespace LaraDBChat\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaraDBChat\Services\LaraDBChatService;

class LaraDBChatController extends Controller
{
    public function __construct(
        protected LaraDBChatService $service
    ) {}

    /**
     * Ask a natural language question.
     */
    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:1000',
            'execute' => 'sometimes|boolean',
        ]);

        $question = $request->input('question');

        try {
            if ($request->input('execute', true) === false) {
                $sql = $this->service->generateSql($question);

                return response()->json([
                    'success' => true,
                    'question' => $question,
                    'sql' => $sql,
                    'executed' => false,
                ]);
            }

            $result = $this->service->ask($question);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'question' => $question,
            ], 500);
        }
    }

    /**
     * Trigger training on the database schema.
     */
    public function train(Request $request): JsonResponse
    {
        $request->validate([
            'fresh' => 'sometimes|boolean',
        ]);

        try {
            if ($request->input('fresh', false)) {
                $this->service->clearTraining();
            }

            $result = $this->service->train();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success']
                    ? 'Training completed successfully'
                    : 'Training completed with errors',
                'tables_trained' => $result['tables_trained'],
                'total_tables' => $result['total_tables'],
                'errors' => $result['errors'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get query history.
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
        ]);

        $limit = $request->input('limit', 50);
        $offset = $request->input('offset', 0);

        $history = $this->service->getHistory($limit, $offset);

        return response()->json([
            'success' => true,
            'data' => $history,
            'count' => count($history),
        ]);
    }

    /**
     * Get training status.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'training' => $this->service->getTrainingStatus(),
            'provider' => $this->service->getProvider(),
        ]);
    }

    /**
     * Get database schema.
     */
    public function schema(): JsonResponse
    {
        try {
            $schema = $this->service->getSchema();

            return response()->json([
                'success' => true,
                'tables' => array_keys($schema),
                'schema' => $schema,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate a SQL query without executing it.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'sql' => 'required|string',
        ]);

        $result = $this->service->validateSql($request->input('sql'));

        return response()->json($result);
    }

    /**
     * Add a sample query for training.
     */
    public function addSample(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'sql' => 'required|string|max:2000',
        ]);

        try {
            $this->service->addSampleQuery(
                $request->input('question'),
                $request->input('sql')
            );

            return response()->json([
                'success' => true,
                'message' => 'Sample query added successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
