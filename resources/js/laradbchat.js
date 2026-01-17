/**
 * LaraDBChat Widget - Alpine.js Component
 * A floating chat widget for database queries using natural language
 */

function laraDBChatWidget(config) {
    return {
        // State
        isOpen: false,
        isLoading: false,
        messages: [],
        currentMessage: '',

        // Configuration
        apiUrl: config.apiUrl || '/api/laradbchat',
        csrfToken: config.csrfToken || '',
        showSql: config.showSql !== false,
        maxHistory: config.maxHistory || 50,

        /**
         * Initialize the widget
         */
        init() {
            this.loadHistory();
        },

        /**
         * Toggle the chat window open/closed
         */
        toggle() {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.$nextTick(() => {
                    this.scrollToBottom();
                    // Focus the input
                    const input = this.$el.querySelector('.ldc-input');
                    if (input) input.focus();
                });
            }
        },

        /**
         * Send a message to the API
         */
        async sendMessage() {
            const message = this.currentMessage.trim();
            if (!message || this.isLoading) return;

            // Add user message
            this.messages.push({
                type: 'user',
                content: message,
                timestamp: new Date().toISOString()
            });

            this.currentMessage = '';
            this.isLoading = true;
            this.scrollToBottom();

            try {
                const response = await fetch(`${this.apiUrl}/ask`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ question: message })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                // Add bot response
                this.messages.push({
                    type: 'bot',
                    content: data,
                    timestamp: new Date().toISOString()
                });

            } catch (error) {
                console.error('LaraDBChat Error:', error);
                this.messages.push({
                    type: 'bot',
                    content: {
                        success: false,
                        error: 'Failed to connect to the server. Please try again.'
                    },
                    timestamp: new Date().toISOString()
                });
            }

            this.isLoading = false;
            this.scrollToBottom();
            this.saveHistory();
        },

        /**
         * Format a message for display
         */
        formatMessage(message) {
            if (message.type === 'user') {
                return this.escapeHtml(message.content);
            }

            const data = message.content;
            let html = '';

            if (data.success) {
                // Show result count
                if (data.count !== null && data.count !== undefined) {
                    html += `<div class="ldc-result-count">Found ${data.count} result${data.count !== 1 ? 's' : ''}</div>`;
                }

                // Show SQL if enabled
                if (this.showSql && data.sql) {
                    html += `<div class="ldc-sql-block"><code>${this.escapeHtml(data.sql)}</code></div>`;
                }

                // Show data
                if (data.data && data.data.length > 0) {
                    html += this.formatDataTable(data.data);
                } else if (data.count === 0) {
                    html += '<div class="ldc-no-results">No results found.</div>';
                }

                // Show execution time
                if (data.execution_time) {
                    html += `<div class="ldc-timing">Query executed in ${data.execution_time}s</div>`;
                }
            } else {
                html += `<div class="ldc-error">${this.escapeHtml(data.error || 'An error occurred')}</div>`;
            }

            return html;
        },

        /**
         * Format data as an HTML table
         */
        formatDataTable(data) {
            if (!data || data.length === 0) return '';

            const headers = Object.keys(data[0]);
            const maxRows = 10;
            const displayData = data.slice(0, maxRows);

            let html = '<div class="ldc-table-wrapper"><table class="ldc-table">';

            // Headers
            html += '<thead><tr>';
            headers.forEach(h => {
                html += `<th>${this.escapeHtml(h)}</th>`;
            });
            html += '</tr></thead>';

            // Body
            html += '<tbody>';
            displayData.forEach(row => {
                html += '<tr>';
                headers.forEach(h => {
                    const value = row[h];
                    let displayValue;

                    if (value === null) {
                        displayValue = '<em style="color: #9ca3af">null</em>';
                    } else if (typeof value === 'object') {
                        displayValue = this.escapeHtml(JSON.stringify(value));
                    } else {
                        const strValue = String(value);
                        // Truncate long values
                        displayValue = strValue.length > 50
                            ? this.escapeHtml(strValue.substring(0, 47)) + '...'
                            : this.escapeHtml(strValue);
                    }
                    html += `<td>${displayValue}</td>`;
                });
                html += '</tr>';
            });
            html += '</tbody></table></div>';

            if (data.length > maxRows) {
                html += `<div class="ldc-more-results">Showing ${maxRows} of ${data.length} results</div>`;
            }

            return html;
        },

        /**
         * Escape HTML special characters
         */
        escapeHtml(text) {
            if (typeof text !== 'string') return text;
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Scroll the messages container to the bottom
         */
        scrollToBottom() {
            this.$nextTick(() => {
                const container = this.$refs.messages;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },

        /**
         * Load chat history from localStorage
         */
        loadHistory() {
            try {
                const saved = localStorage.getItem('laradbchat_history');
                if (saved) {
                    const history = JSON.parse(saved);
                    this.messages = history.slice(-this.maxHistory);
                }
            } catch (e) {
                console.warn('LaraDBChat: Failed to load chat history:', e);
            }
        },

        /**
         * Save chat history to localStorage
         */
        saveHistory() {
            try {
                const toSave = this.messages.slice(-this.maxHistory);
                localStorage.setItem('laradbchat_history', JSON.stringify(toSave));
            } catch (e) {
                console.warn('LaraDBChat: Failed to save chat history:', e);
            }
        },

        /**
         * Clear all chat history
         */
        clearHistory() {
            this.messages = [];
            localStorage.removeItem('laradbchat_history');
        },

        /**
         * Check if there are messages to display
         */
        hasMessages() {
            return this.messages.length > 0;
        }
    };
}

// Auto-register with window for global access
if (typeof window !== 'undefined') {
    window.laraDBChatWidget = laraDBChatWidget;
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = laraDBChatWidget;
}
