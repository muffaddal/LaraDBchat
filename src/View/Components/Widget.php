<?php

namespace LaraDBChat\View\Components;

use Illuminate\View\Component;

class Widget extends Component
{
    public string $position;
    public string $title;
    public string $placeholder;
    public bool $showSql;
    public array $theme;

    /**
     * Create a new component instance.
     */
    public function __construct(
        ?string $position = null,
        ?string $title = null,
        ?string $placeholder = null,
        ?bool $showSql = null,
        ?array $theme = null
    ) {
        $this->position = $position ?? config('laradbchat.widget.position', 'bottom-right');
        $this->title = $title ?? config('laradbchat.widget.title', 'Database Assistant');
        $this->placeholder = $placeholder ?? config('laradbchat.widget.placeholder', 'Ask a question about your data...');
        $this->showSql = $showSql ?? config('laradbchat.widget.show_sql', true);
        $this->theme = $theme ?? config('laradbchat.widget.theme', []);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('laradbchat::components.widget');
    }

    /**
     * Determine if the widget should be rendered.
     */
    public function shouldRender(): bool
    {
        return config('laradbchat.widget.enabled', true);
    }
}
