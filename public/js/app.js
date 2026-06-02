/**
 * Task Manager SPA — Vanilla JavaScript
 *
 * Modules: Api, Auth, Tasks, Toast, App
 */

'use strict';

/* ---------------------------------------------------------------
   API Client
   --------------------------------------------------------------- */

const Api = {
    TOKEN_KEY: 'tm_token',
    USER_KEY: 'tm_user',

    getToken() {
        return localStorage.getItem(this.TOKEN_KEY);
    },

    setToken(token) {
        localStorage.setItem(this.TOKEN_KEY, token);
    },

    setUser(user) {
        localStorage.setItem(this.USER_KEY, JSON.stringify(user));
    },

    getUser() {
        try {
            return JSON.parse(localStorage.getItem(this.USER_KEY));
        } catch {
            return null;
        }
    },

    clearAuth() {
        localStorage.removeItem(this.TOKEN_KEY);
        localStorage.removeItem(this.USER_KEY);
    },

    isAuthenticated() {
        return !!this.getToken();
    },

    async request(endpoint, options = {}) {
        const headers = { 'Content-Type': 'application/json' };
        const token = this.getToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const response = await fetch(endpoint, {
            ...options,
            headers: { ...headers, ...options.headers },
        });

        // Handle 204 No Content
        if (response.status === 204) {
            return { success: true, data: null };
        }

        // Handle CSV export (non-JSON)
        const contentType = response.headers.get('Content-Type') || '';
        if (contentType.includes('text/csv')) {
            const blob = await response.blob();
            return { success: true, data: blob, isBlob: true };
        }

        const data = await response.json();

        // Auto-logout on 401
        if (response.status === 401) {
            this.clearAuth();
            App.render();
            throw new Error(data.message || 'Session expired');
        }

        if (!data.success) {
            throw new Error(data.message || 'Request failed');
        }

        return data;
    },

    // Auth
    login(username, password) {
        return this.request('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ username, password }),
        });
    },

    register(username, password) {
        return this.request('/auth/register', {
            method: 'POST',
            body: JSON.stringify({ username, password }),
        });
    },

    // Tasks
    getTasks(params = {}) {
        const query = new URLSearchParams();
        for (const [key, value] of Object.entries(params)) {
            if (value !== '' && value !== undefined && value !== null) {
                query.set(key, value);
            }
        }
        const qs = query.toString();
        return this.request(`/tasks${qs ? '?' + qs : ''}`);
    },

    createTask(data) {
        return this.request('/tasks', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    },

    updateTask(id, data) {
        return this.request(`/tasks/${id}`, {
            method: 'PATCH',
            body: JSON.stringify(data),
        });
    },

    completeTask(id) {
        return this.request(`/tasks/${id}/complete`, { method: 'PATCH' });
    },

    deleteTask(id) {
        return this.request(`/tasks/${id}`, { method: 'DELETE' });
    },

    searchTasks(q, params = {}) {
        const query = new URLSearchParams({ q, ...params });
        return this.request(`/tasks/search?${query}`);
    },

    getStats() {
        return this.request('/tasks/stats');
    },

    getExport(format) {
        return this.request(`/tasks/export?format=${format}`);
    },

    // Tags
    getTags() {
        return this.request('/tags');
    },

    createTag(name, color) {
        return this.request('/tags', {
            method: 'POST',
            body: JSON.stringify({ name, color }),
        });
    },

    updateTag(id, data) {
        return this.request(`/tags/${id}`, {
            method: 'PATCH',
            body: JSON.stringify(data),
        });
    },

    deleteTag(id) {
        return this.request(`/tags/${id}`, { method: 'DELETE' });
    },

    syncTaskTags(taskId, tagIds) {
        return this.request(`/tasks/${taskId}/tags`, {
            method: 'POST',
            body: JSON.stringify({ tag_ids: tagIds }),
        });
    },

    bulkComplete(ids) {
        return this.request('/tasks/bulk-complete', {
            method: 'POST',
            body: JSON.stringify({ ids }),
        });
    },

    bulkDelete(ids) {
        return this.request('/tasks/bulk-delete', {
            method: 'POST',
            body: JSON.stringify({ ids }),
        });
    },
};

/* ---------------------------------------------------------------
   Toast Notifications
   --------------------------------------------------------------- */

const Toast = {
    show(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.textContent = message;
        container.appendChild(el);
        setTimeout(() => {
            el.remove();
        }, 3500);
    },

    success(msg) {
        this.show(msg, 'success');
    },

    error(msg) {
        this.show(msg, 'error');
    },
};

/* ---------------------------------------------------------------
   Auth Module
   --------------------------------------------------------------- */

const Auth = {
    render() {
        const app = document.getElementById('app');
        app.innerHTML = `
            <div class="auth-container">
                <div class="auth-card">
                    <div class="text-center">
                        <h2>Task Manager</h2>
                        <p class="subtitle">Manage your tasks efficiently</p>
                    </div>
                    <div class="auth-tabs">
                        <button class="auth-tab active" data-tab="login">Login</button>
                        <button class="auth-tab" data-tab="register">Register</button>
                    </div>
                    <div id="auth-error" class="auth-error hidden"></div>
                    <form id="auth-form">
                        <div class="form-group">
                            <label for="auth-username">Username</label>
                            <input type="text" id="auth-username" name="username"
                                   minlength="3" maxlength="50" required
                                   placeholder="Enter username">
                        </div>
                        <div class="form-group">
                            <label for="auth-password">Password</label>
                            <input type="password" id="auth-password" name="password"
                                   minlength="6" required
                                   placeholder="Enter password">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%"
                                id="auth-submit">Login</button>
                    </form>
                </div>
            </div>
        `;
        this.bind();
    },

    bind() {
        let mode = 'login';

        document.querySelectorAll('.auth-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                mode = tab.dataset.tab;
                document.querySelectorAll('.auth-tab').forEach((t) => t.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById('auth-submit').textContent =
                    mode === 'login' ? 'Login' : 'Register';
                document.getElementById('auth-error').classList.add('hidden');
            });
        });

        document.getElementById('auth-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('auth-username').value.trim();
            const password = document.getElementById('auth-password').value;
            const errorEl = document.getElementById('auth-error');
            const submitBtn = document.getElementById('auth-submit');

            submitBtn.disabled = true;
            errorEl.classList.add('hidden');

            try {
                if (mode === 'register') {
                    await Api.register(username, password);
                    Toast.success('Account created! Logging in...');
                }

                const result = await Api.login(username, password);
                Api.setToken(result.data.token);
                Api.setUser(result.data.user);
                App.render();
            } catch (err) {
                errorEl.textContent = err.message;
                errorEl.classList.remove('hidden');
            } finally {
                submitBtn.disabled = false;
            }
        });
    },
};

/* ---------------------------------------------------------------
   Tasks Module
   --------------------------------------------------------------- */

const Tasks = {
    state: {
        tasks: [],
        pagination: null,
        stats: null,
        allTags: [],
        filters: {
            status: 'all',
            priority: '',
            sort: 'created_at',
            order: 'desc',
            page: 1,
            per_page: 15,
        },
        search: '',
        editingTask: null,
        selectedIds: new Set(),
    },

    render() {
        const app = document.getElementById('app');
        app.innerHTML = `
            <div class="tasks-container">
                <div id="stats-bar" class="stats-bar"></div>
                <div class="toolbar">
                    <div class="toolbar-search">
                        <input type="text" id="search-input"
                               placeholder="Search tasks..." value="${this.escapeHtml(this.state.search)}">
                    </div>
                    <select id="filter-status">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                    </select>
                    <select id="filter-priority">
                        <option value="">All Priorities</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                    <select id="filter-sort">
                        <option value="created_at-desc">Newest First</option>
                        <option value="created_at-asc">Oldest First</option>
                        <option value="priority-desc">Priority (High-Low)</option>
                        <option value="priority-asc">Priority (Low-High)</option>
                        <option value="due_date-asc">Due Date (Soonest)</option>
                        <option value="title-asc">Title (A-Z)</option>
                    </select>
                    <div class="toolbar-actions">
                        <button class="btn btn-outline btn-sm" id="btn-tags" title="Manage Tags">Tags</button>
                        <button class="btn btn-outline btn-sm" id="btn-export" title="Export">Export</button>
                        <button class="btn btn-primary btn-sm" id="btn-new-task">+ New Task</button>
                    </div>
                </div>
                <div id="bulk-bar" class="bulk-bar hidden">
                    <span id="bulk-count">0 selected</span>
                    <div class="bulk-actions">
                        <button type="button" id="btn-bulk-complete" class="btn-bulk-action">Complete selected</button>
                        <button type="button" id="btn-bulk-delete" class="btn-bulk-action btn-danger">Delete selected</button>
                        <button type="button" id="btn-bulk-cancel" class="btn-bulk-action">Clear</button>
                    </div>
                </div>
                <div id="task-list" class="task-list">
                    <div class="loading-container"><span class="spinner"></span></div>
                </div>
                <div id="pagination" class="pagination"></div>
            </div>
        `;

        // Restore filter values in selects
        document.getElementById('filter-status').value = this.state.filters.status;
        document.getElementById('filter-priority').value = this.state.filters.priority;
        document.getElementById('filter-sort').value =
            `${this.state.filters.sort}-${this.state.filters.order}`;

        this.bind();
        this.loadTags();
        this.loadStats();
        this.loadTasks();
    },

    bind() {
        // New task
        document.getElementById('btn-new-task').addEventListener('click', () => {
            this.openTaskModal();
        });

        // Tags management
        document.getElementById('btn-tags').addEventListener('click', () => {
            this.openTagsModal();
        });

        // Search (debounced)
        let searchTimeout;
        document.getElementById('search-input').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.state.search = e.target.value.trim();
                this.state.filters.page = 1;
                this.loadTasks();
            }, 400);
        });

        // Filters
        document.getElementById('filter-status').addEventListener('change', (e) => {
            this.state.filters.status = e.target.value;
            this.state.filters.page = 1;
            this.loadTasks();
        });

        document.getElementById('filter-priority').addEventListener('change', (e) => {
            this.state.filters.priority = e.target.value;
            this.state.filters.page = 1;
            this.loadTasks();
        });

        document.getElementById('filter-sort').addEventListener('change', (e) => {
            const [sort, order] = e.target.value.split('-');
            this.state.filters.sort = sort;
            this.state.filters.order = order;
            this.state.filters.page = 1;
            this.loadTasks();
        });

        // Export
        document.getElementById('btn-export').addEventListener('click', () => {
            this.showExportMenu();
        });

        // Task form
        document.getElementById('task-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveTask();
        });

        // Modal close
        document.getElementById('modal-close').addEventListener('click', () => this.closeTaskModal());
        document.getElementById('modal-cancel').addEventListener('click', () => this.closeTaskModal());
        document.getElementById('task-modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) this.closeTaskModal();
        });

        // Delete modal
        document.getElementById('delete-modal-close').addEventListener('click', () => this.closeDeleteModal());
        document.getElementById('delete-cancel').addEventListener('click', () => this.closeDeleteModal());
        document.getElementById('delete-modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) this.closeDeleteModal();
        });

        // Tags modal
        document.getElementById('tags-modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) this.closeTagsModal();
        });
        document.getElementById('tags-modal-close').addEventListener('click', () => this.closeTagsModal());

        // Bulk action bar
        document.getElementById('btn-bulk-complete').addEventListener('click', () => this.bulkCompleteSelected());
        document.getElementById('btn-bulk-delete').addEventListener('click', () => this.bulkDeleteSelected());
        document.getElementById('btn-bulk-cancel').addEventListener('click', () => this.clearBulkSelection());
    },

    // Tags
    async loadTags() {
        try {
            const result = await Api.getTags();
            this.state.allTags = result.data || [];
        } catch {
            this.state.allTags = [];
        }
    },

    renderTagCheckboxes(selectedTagIds = []) {
        const container = document.getElementById('task-tags-list');
        if (!container) return;

        if (this.state.allTags.length === 0) {
            container.innerHTML = '<span class="text-secondary" style="font-size:0.84rem">No tags yet. Create tags from the Tags button.</span>';
            return;
        }

        container.innerHTML = this.state.allTags.map((tag) => `
            <label class="tag-checkbox-label" style="--tag-color: ${tag.color}">
                <input type="checkbox" name="tag" value="${tag.id}"
                       ${selectedTagIds.includes(tag.id) ? 'checked' : ''}>
                <span class="tag-pill" style="background: ${tag.color}20; color: ${tag.color}; border-color: ${tag.color}">${this.escapeHtml(tag.name)}</span>
            </label>
        `).join('');
    },

    getSelectedTagIds() {
        const checkboxes = document.querySelectorAll('#task-tags-list input[name="tag"]:checked');
        return Array.from(checkboxes).map((cb) => parseInt(cb.value, 10));
    },

    // Tags management modal
    openTagsModal() {
        const modal = document.getElementById('tags-modal');
        modal.classList.remove('hidden');
        this.renderTagsManager();
    },

    closeTagsModal() {
        document.getElementById('tags-modal').classList.add('hidden');
    },

    renderTagsManager() {
        const container = document.getElementById('tags-manager-list');

        let html = `
            <div class="tag-create-row">
                <input type="text" id="new-tag-name" placeholder="Tag name" maxlength="30" class="tag-input">
                <input type="color" id="new-tag-color" value="#6b7280" class="tag-color-input">
                <button type="button" id="btn-create-tag" class="btn btn-primary btn-sm">Add</button>
            </div>
        `;

        if (this.state.allTags.length === 0) {
            html += '<p class="text-secondary text-center" style="margin-top:1rem;font-size:0.85rem">No tags yet. Create your first tag above.</p>';
        } else {
            html += '<div class="tags-list-manage">';
            html += this.state.allTags.map((tag) => `
                <div class="tag-manage-item">
                    <span class="tag-pill" style="background: ${tag.color}20; color: ${tag.color}; border-color: ${tag.color}">${this.escapeHtml(tag.name)}</span>
                    <button class="btn-icon tag-delete-btn" data-tag-id="${tag.id}" title="Delete tag">&times;</button>
                </div>
            `).join('');
            html += '</div>';
        }

        container.innerHTML = html;

        // Bind create
        document.getElementById('btn-create-tag').addEventListener('click', () => this.createTag());
        document.getElementById('new-tag-name').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.createTag();
            }
        });

        // Bind deletes
        container.querySelectorAll('.tag-delete-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const tagId = parseInt(btn.dataset.tagId, 10);
                this.deleteTag(tagId);
            });
        });
    },

    async createTag() {
        const nameInput = document.getElementById('new-tag-name');
        const colorInput = document.getElementById('new-tag-color');
        const name = nameInput.value.trim();
        if (!name) return;

        try {
            await Api.createTag(name, colorInput.value);
            await this.loadTags();
            this.renderTagsManager();
            nameInput.value = '';
            Toast.success(`Tag "${name}" created`);
        } catch (err) {
            Toast.error(err.message);
        }
    },

    async deleteTag(tagId) {
        try {
            await Api.deleteTag(tagId);
            await this.loadTags();
            this.renderTagsManager();
            this.loadTasks(); // Refresh tasks since tags may have changed
            Toast.success('Tag deleted');
        } catch (err) {
            Toast.error(err.message);
        }
    },

    async loadStats() {
        try {
            const result = await Api.getStats();
            this.state.stats = result.data;
            this.renderStats();
        } catch {
            // Stats are non-critical
        }
    },

    renderStats() {
        const s = this.state.stats;
        if (!s) return;

        document.getElementById('stats-bar').innerHTML = `
            <div class="stat-card">
                <div class="stat-value">${s.total}</div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card stat-completed">
                <div class="stat-value">${s.completed}</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card stat-pending">
                <div class="stat-value">${s.pending}</div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${Math.round(s.completion_percentage)}%</div>
                <div class="stat-label">Progress</div>
            </div>
        `;
    },

    async loadTasks() {
        const listEl = document.getElementById('task-list');
        listEl.innerHTML = '<div class="loading-container"><span class="spinner"></span></div>';

        try {
            let result;
            if (this.state.search) {
                result = await Api.searchTasks(this.state.search, {
                    page: this.state.filters.page,
                    per_page: this.state.filters.per_page,
                });
            } else {
                result = await Api.getTasks(this.state.filters);
            }

            this.state.tasks = result.data;
            this.state.pagination = result.pagination || null;
            this.renderTaskList();
            this.renderPagination();
        } catch (err) {
            listEl.innerHTML = `<div class="empty-state"><p>Failed to load tasks</p>
                <p class="hint">${this.escapeHtml(err.message)}</p></div>`;
        }
    },

    renderTaskList() {
        const listEl = document.getElementById('task-list');

        if (!this.state.tasks.length) {
            listEl.innerHTML = `<div class="empty-state">
                <p>No tasks found</p>
                <p class="hint">${this.state.search ? 'Try a different search term' : 'Create your first task!'}</p>
            </div>`;
            return;
        }

        this.state.selectedIds.clear();
        listEl.innerHTML = this.state.tasks.map((task) => this.renderTaskCard(task)).join('');

        // Bind task actions
        listEl.querySelectorAll('[data-action]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.action;
                const id = parseInt(btn.dataset.id, 10);
                const task = this.state.tasks.find((t) => t.id === id);
                if (!task) return;

                if (action === 'complete') this.toggleComplete(task);
                else if (action === 'edit') this.openTaskModal(task);
                else if (action === 'delete') this.openDeleteModal(task);
            });
        });

        // Checkbox click (completion toggle)
        listEl.querySelectorAll('.task-checkbox').forEach((cb) => {
            cb.addEventListener('change', () => {
                const id = parseInt(cb.dataset.id, 10);
                const task = this.state.tasks.find((t) => t.id === id);
                if (task && task.status === 'pending') {
                    this.toggleComplete(task);
                } else {
                    cb.checked = true; // Can't un-complete via checkbox
                }
            });
        });

        // Bulk select checkboxes
        listEl.querySelectorAll('.bulk-checkbox').forEach((cb) => {
            cb.addEventListener('change', () => {
                const id = parseInt(cb.dataset.id, 10);
                if (cb.checked) {
                    this.state.selectedIds.add(id);
                } else {
                    this.state.selectedIds.delete(id);
                }
                this.updateBulkBar();
            });
        });

        this.updateBulkBar();
    },

    renderTaskCard(task) {
        const isCompleted = task.status === 'completed';
        const dueInfo = this.formatDueDate(task.due_date);
        const tags = task.tags || [];

        const tagsHtml = tags.length > 0
            ? `<span class="task-tags">${tags.map((t) =>
                `<span class="tag-pill tag-pill-sm" style="background:${t.color}20;color:${t.color};border-color:${t.color}">${this.escapeHtml(t.name)}</span>`
            ).join('')}</span>`
            : '';

        const recurBadge = task.recurrence && task.recurrence !== 'none'
            ? `<span class="badge badge-recurrence" title="Repeats ${task.recurrence}">&#8635; ${task.recurrence}</span>`
            : '';

        return `
            <div class="task-card priority-${task.priority} status-${task.status}">
                <div class="task-bulk">
                    <input type="checkbox" class="bulk-checkbox" data-id="${task.id}">
                </div>
                <div class="task-check">
                    <input type="checkbox" class="task-checkbox"
                           data-id="${task.id}"
                           ${isCompleted ? 'checked' : ''}
                           ${isCompleted ? 'disabled' : ''}>
                </div>
                <div class="task-body">
                    <div class="task-title">${this.escapeHtml(task.title)}</div>
                    ${task.description ? `<div class="task-description">${this.escapeHtml(task.description)}</div>` : ''}
                    <div class="task-meta">
                        <span class="badge badge-${task.priority}">${task.priority}</span>
                        <span class="badge badge-${task.status}">${task.status}</span>
                        ${dueInfo ? `<span class="task-due ${dueInfo.overdue ? 'overdue' : ''}">${dueInfo.text}</span>` : ''}
                        ${tagsHtml}
                        ${recurBadge}
                    </div>
                </div>
                <div class="task-actions">
                    ${!isCompleted ? `<button class="btn-icon" data-action="complete" data-id="${task.id}" title="Complete">&#10003;</button>` : ''}
                    <button class="btn-icon" data-action="edit" data-id="${task.id}" title="Edit">&#9998;</button>
                    <button class="btn-icon" data-action="delete" data-id="${task.id}" title="Delete">&#128465;</button>
                </div>
            </div>
        `;
    },

    formatDueDate(dueDate) {
        if (!dueDate) return null;

        const due = new Date(dueDate + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const diffDays = Math.ceil((due - today) / (1000 * 60 * 60 * 24));
        const formatted = due.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
        });

        if (diffDays < 0) {
            return { text: `Overdue: ${formatted}`, overdue: true };
        } else if (diffDays === 0) {
            return { text: `Due today`, overdue: false };
        } else if (diffDays === 1) {
            return { text: `Due tomorrow`, overdue: false };
        }
        return { text: `Due: ${formatted}`, overdue: false };
    },

    renderPagination() {
        const pagEl = document.getElementById('pagination');
        const pag = this.state.pagination;

        if (!pag || pag.total_pages <= 1) {
            pagEl.innerHTML = '';
            return;
        }

        pagEl.innerHTML = `
            <button class="btn btn-outline btn-sm" id="page-prev"
                    ${pag.page <= 1 ? 'disabled' : ''}>Prev</button>
            <span class="pagination-info">Page ${pag.page} of ${pag.total_pages}</span>
            <button class="btn btn-outline btn-sm" id="page-next"
                    ${pag.page >= pag.total_pages ? 'disabled' : ''}>Next</button>
        `;

        document.getElementById('page-prev').addEventListener('click', () => {
            this.state.filters.page--;
            this.loadTasks();
        });

        document.getElementById('page-next').addEventListener('click', () => {
            this.state.filters.page++;
            this.loadTasks();
        });
    },

    // Task modal
    openTaskModal(task = null) {
        this.state.editingTask = task;
        const modal = document.getElementById('task-modal');
        const form = document.getElementById('task-form');

        document.getElementById('modal-title').textContent = task ? 'Edit Task' : 'New Task';

        form.reset();
        if (task) {
            document.getElementById('task-title').value = task.title;
            document.getElementById('task-description').value = task.description || '';
            document.getElementById('task-priority').value = task.priority;
            document.getElementById('task-due-date').value = task.due_date || '';
            document.getElementById('task-recurrence').value = task.recurrence || 'none';
        }

        // Render tag checkboxes
        const selectedTagIds = task && task.tags ? task.tags.map((t) => t.id) : [];
        this.renderTagCheckboxes(selectedTagIds);

        modal.classList.remove('hidden');
        document.getElementById('task-title').focus();
    },

    closeTaskModal() {
        document.getElementById('task-modal').classList.add('hidden');
        this.state.editingTask = null;
    },

    async saveTask() {
        const data = {
            title: document.getElementById('task-title').value.trim(),
            description: document.getElementById('task-description').value.trim(),
            priority: document.getElementById('task-priority').value,
            due_date: document.getElementById('task-due-date').value || '',
            recurrence: document.getElementById('task-recurrence').value,
            tag_ids: this.getSelectedTagIds(),
        };

        if (!data.title) return;

        try {
            if (this.state.editingTask) {
                await Api.updateTask(this.state.editingTask.id, data);
                Toast.success('Task updated');
            } else {
                await Api.createTask(data);
                Toast.success('Task created');
            }
            this.closeTaskModal();
            this.loadTasks();
            this.loadStats();
        } catch (err) {
            Toast.error(err.message);
        }
    },

    // Complete
    async toggleComplete(task) {
        try {
            await Api.completeTask(task.id);
            Toast.success('Task completed!');
            this.loadTasks();
            this.loadStats();
        } catch (err) {
            Toast.error(err.message);
        }
    },

    // Delete modal
    openDeleteModal(task) {
        this.state.editingTask = task;
        document.getElementById('delete-task-name').textContent = task.title;
        document.getElementById('delete-modal').classList.remove('hidden');

        const confirmBtn = document.getElementById('delete-confirm');
        // Remove old listener by cloning
        const newBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
        newBtn.addEventListener('click', () => this.confirmDelete());
    },

    closeDeleteModal() {
        document.getElementById('delete-modal').classList.add('hidden');
    },

    async confirmDelete() {
        if (!this.state.editingTask) return;
        try {
            await Api.deleteTask(this.state.editingTask.id);
            Toast.success('Task deleted');
            this.closeDeleteModal();
            this.loadTasks();
            this.loadStats();
        } catch (err) {
            Toast.error(err.message);
        }
    },

    // Export
    async showExportMenu() {
        const btn = document.getElementById('btn-export');
        try {
            btn.disabled = true;

            const jsonResult = await Api.getExport('json');
            const blob = new Blob([JSON.stringify(jsonResult.data, null, 2)], {
                type: 'application/json',
            });
            this.downloadBlob(blob, 'tasks-export.json');
            Toast.success('Exported as JSON');
        } catch (err) {
            Toast.error(err.message);
        } finally {
            btn.disabled = false;
        }
    },

    downloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    },

    escapeHtml(str) {
        if (!str) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return str.replace(/[&<>"']/g, (c) => map[c]);
    },

    // Bulk selection
    updateBulkBar() {
        const bar = document.getElementById('bulk-bar');
        if (!bar) return;
        const count = this.state.selectedIds.size;
        if (count > 0) {
            bar.classList.remove('hidden');
            document.getElementById('bulk-count').textContent =
                `${count} task${count !== 1 ? 's' : ''} selected`;
        } else {
            bar.classList.add('hidden');
        }
    },

    clearBulkSelection() {
        this.state.selectedIds.clear();
        document.querySelectorAll('.bulk-checkbox').forEach((cb) => {
            cb.checked = false;
        });
        this.updateBulkBar();
    },

    async bulkCompleteSelected() {
        const ids = [...this.state.selectedIds];
        if (ids.length === 0) return;
        try {
            const result = await Api.bulkComplete(ids);
            const { affected, skipped } = result.data;
            Toast.success(`${affected} task${affected !== 1 ? 's' : ''} completed`);
            if (skipped && skipped.length > 0) {
                Toast.show(`${skipped.length} already completed or not found`, 'info');
            }
            this.loadTasks();
            this.loadStats();
        } catch (err) {
            Toast.error(err.message);
        }
    },

    async bulkDeleteSelected() {
        const ids = [...this.state.selectedIds];
        if (ids.length === 0) return;
        try {
            const result = await Api.bulkDelete(ids);
            const { affected } = result.data;
            Toast.success(`${affected} task${affected !== 1 ? 's' : ''} deleted`);
            this.loadTasks();
            this.loadStats();
        } catch (err) {
            Toast.error(err.message);
        }
    },
};

/* ---------------------------------------------------------------
   App Controller
   --------------------------------------------------------------- */

const App = {
    render() {
        const header = document.getElementById('header');

        if (Api.isAuthenticated()) {
            header.classList.remove('hidden');
            const user = Api.getUser();
            document.getElementById('header-user').textContent = user ? user.username : '';
            Tasks.render();
        } else {
            header.classList.add('hidden');
            Auth.render();
        }
    },

    init() {
        // Logout
        document.getElementById('btn-logout').addEventListener('click', () => {
            Api.clearAuth();
            Tasks.state.search = '';
            Tasks.state.filters.page = 1;
            App.render();
            Toast.show('Logged out');
        });

        this.render();
    },
};

/* ---------------------------------------------------------------
   Bootstrap
   --------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});
