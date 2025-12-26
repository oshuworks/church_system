(() => {
  const apiBase = 'api';
  const toastContainer = document.getElementById('toast-container');
  const seenAlertIds = new Set();
  const toastQueue = [];
  let isShowingToast = false;

  const moneyFmt = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PHP' });
  const percentFmt = (value) => `${Number(value ?? 0).toFixed(0)}%`;
  let projectsCache = [];
  let tasksCache = [];
  let employeesCache = [];
  let equipmentCache = [];
  let budgetsCache = [];

  function processToastQueue() {
    if (isShowingToast || !toastQueue.length || !toastContainer) return;
    isShowingToast = true;
    const { title, message, variant } = toastQueue.shift();
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-bg-${variant} border-0 mb-2`;
    toastEl.role = 'alert';
    toastEl.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">
          <strong>${title}</strong><br>
          <span class="small">${message}</span>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    `;
    toastContainer.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toastEl.addEventListener('hidden.bs.toast', () => {
      toastEl.remove();
      isShowingToast = false;
      processToastQueue();
    });
    toast.show();
  }

  function showToast(title, message, variant = 'primary') {
    toastQueue.push({ title, message, variant });
    processToastQueue();
  }

  async function fetchJSON(endpoint, options = {}) {
    const res = await fetch(`${apiBase}/${endpoint}`, options);
    if (!res.ok) {
      const text = await res.text();
      throw new Error(text || `Request failed (${res.status})`);
    }
    return res.json();
  }

  function renderSummary(data) {
    const { projects, tasks, budget, alerts, utilization, resources } = data;
    const setText = (id, value) => {
      const el = document.getElementById(id);
      if (el) el.textContent = value;
    };

    setText('projects-total', projects?.total_projects ?? '--');
    setText('projects-active', projects?.active_projects ?? '--');
    setText('projects-completed', projects?.completed_projects ?? '--');

    setText('tasks-total', tasks?.total_tasks ?? '--');
    setText('tasks-in-progress', tasks?.in_progress_tasks ?? '--');
    setText('tasks-completed', tasks?.completed_tasks ?? '--');

    setText('budget-allocated', budget ? moneyFmt.format(budget.total_allocated) : '--');
    setText('budget-spent', budget ? moneyFmt.format(budget.total_spent) : '--');

    const critical = Number(alerts?.critical ?? 0);
    const high = Number(alerts?.high ?? 0);
    const medium = Number(alerts?.medium ?? 0);
    const low = Number(alerts?.low ?? 0);
    const totalAlerts = critical + high + medium + low;
    setText('alerts-total', totalAlerts);
    setText('alerts-critical', critical);
    setText('alerts-high', high);
    setText('alerts-medium', medium);
    setText('alerts-low', low);

    // Resource totals (if placeholders added later)
    setText('resources-employees', resources?.employees ?? '--');
    setText('resources-equipment', resources?.equipment ?? '--');
  }

  function workloadBadge(level, percent) {
    const pct = percentFmt(percent ?? 0);
    switch (level) {
      case 'critical':
        return `<span class="utilization-pill util-critical">${pct} · Critical</span>`;
      case 'overloaded':
        return `<span class="utilization-pill util-overloaded">${pct} · Overloaded</span>`;
      case 'optimal':
        return `<span class="utilization-pill util-optimal">${pct} · Optimal</span>`;
      case 'underutilized':
      default:
        return `<span class="utilization-pill util-underutilized">${pct} · Under</span>`;
    }
  }

  function statusPill(status) {
    return `<span class="status-pill status-${status ?? 'unknown'}">${(status ?? 'unknown').replace('_', ' ')}</span>`;
  }

  function priorityChip(priority) {
    return `<span class="priority-chip priority-${priority ?? 'medium'}">${priority ?? 'medium'}</span>`;
  }

  function setSelectOptions(selectEl, items, formatFn, placeholder = 'Select...') {
    if (!selectEl) return;
    selectEl.innerHTML = '';
    const ph = document.createElement('option');
    ph.value = '';
    ph.textContent = placeholder;
    selectEl.appendChild(ph);
    items.forEach((item) => {
      const opt = document.createElement('option');
      opt.value = item.value;
      opt.textContent = formatFn(item);
      selectEl.appendChild(opt);
    });
  }

  function populateProjectSelect() {
    const select = document.getElementById('assign-project');
    if (!select) return;
    const formatted = projectsCache.map((p) => ({
      value: p.project_id,
      label: `${p.project_name} (${p.status})`,
      priority: p.priority,
    }));
    setSelectOptions(
      select,
      formatted,
      (p) => `${p.label} · ${p.priority}`,
      'Choose a project'
    );
    if (formatted.length) {
      select.value = formatted[0].value;
      select.dispatchEvent(new Event('change'));
    }
  }

  function populateTaskSelect(projectId = 0, overrideTasks = null) {
    const select = document.getElementById('assign-task');
    if (!select) return;
    const source = overrideTasks || tasksCache;
    const filtered = projectId ? source.filter((t) => Number(t.project_id) === Number(projectId)) : source;
    const formatted = filtered.map((t) => ({
      value: t.task_id,
      label: `${t.task_name} (${t.priority})`,
    }));
    setSelectOptions(select, formatted, (t) => t.label, 'Choose a task');
    if (formatted.length) select.value = formatted[0].value;
  }

  function renderResourceOptions(data) {
    const typeSelect = document.getElementById('assign-type');
    const resourceSelect = document.getElementById('assign-resource');
    if (!typeSelect || !resourceSelect) return;

    const assignmentEmployees = data.employees || [];
    const assignmentEquipment = data.equipment || [];
    const assignmentBudgets = data.budgets || budgetsCache || [];
    const type = typeSelect.value;

    if (type === 'employee') {
      setSelectOptions(
        resourceSelect,
        assignmentEmployees.map((e) => ({
          value: e.id,
          label: `${e.name} · ${percentFmt(e.utilization_percentage)} · ${e.department || e.role || ''}`,
        })),
        (e) => e.label,
        'Choose an employee'
      );
    } else if (type === 'equipment') {
      setSelectOptions(
        resourceSelect,
        assignmentEquipment.map((eq) => ({
          value: eq.id,
          label: `${eq.name} · ${eq.type} · ${percentFmt(eq.utilization_percentage)}`,
        })),
        (eq) => eq.label,
        'Choose equipment'
      );
    } else {
      setSelectOptions(
        resourceSelect,
        assignmentBudgets.map((b) => ({
          value: b.id,
          label: `${b.name} · Remaining ${moneyFmt.format(b.remaining_amount ?? 0)}`,
        })),
        (b) => b.label,
        'Choose budget'
      );
    }
  }

  function renderAlerts(alerts) {
    const list = document.getElementById('alerts-list');
    const empty = document.getElementById('alerts-empty');
    if (!list || !empty) return;

    list.innerHTML = '';
    if (!alerts.length) {
      empty.classList.remove('d-none');
      return;
    }
    empty.classList.add('d-none');

    alerts.forEach((alert) => {
      const severityClass = `alert-${alert.severity}`;
      const item = document.createElement('a');
      item.className = `list-group-item list-group-item-action ${severityClass}`;
      item.innerHTML = `
        <div class="d-flex w-100 justify-content-between">
          <h6 class="mb-1 text-capitalize">${alert.alert_type.replace('_', ' ')} · ${alert.resource_type}</h6>
          <span class="badge bg-${alert.severity === 'critical' ? 'danger' : alert.severity === 'high' ? 'warning text-dark' : alert.severity === 'medium' ? 'info text-dark' : 'secondary'}">${alert.severity}</span>
        </div>
        <p class="mb-1 small">${alert.message}</p>
        <small class="text-muted">Resource ID: ${alert.resource_id}${alert.project_id ? ` · Project #${alert.project_id}` : ''}${alert.task_id ? ` · Task #${alert.task_id}` : ''}</small>
      `;
      list.appendChild(item);

      if (!seenAlertIds.has(alert.alert_id)) {
        seenAlertIds.add(alert.alert_id);
        const variant = alert.severity === 'critical' ? 'danger' : alert.severity === 'high' ? 'warning' : 'primary';
        showToast(`New ${alert.severity} alert`, alert.message, variant);
      }
    });
  }

  function renderEmployees(rows) {
    const tbody = document.getElementById('employees-body');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No employees found.</td></tr>`;
      return;
    }
    rows.forEach((e) => {
      const utilization = e.latest_utilization ?? e.utilization_percentage;
      tbody.insertAdjacentHTML('beforeend', `
        <tr>
          <td>
            <div class="fw-semibold">${e.employee_name}</div>
            <div class="text-muted small">${e.email}</div>
          </td>
          <td>${e.role}</td>
          <td>${e.department ?? '—'}</td>
          <td>${statusPill(e.status)}</td>
          <td class="text-center">${workloadBadge(e.workload_level ?? 'underutilized', utilization)}</td>
          <td class="text-end">${moneyFmt.format(e.hourly_rate ?? 0)}</td>
        </tr>
      `);
    });
  }

  function renderEquipment(rows) {
    const tbody = document.getElementById('equipment-body');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No equipment found.</td></tr>`;
      return;
    }
    rows.forEach((eq) => {
      const utilization = eq.latest_utilization ?? eq.utilization_percentage;
      tbody.insertAdjacentHTML('beforeend', `
        <tr>
          <td class="fw-semibold">${eq.equipment_name}</td>
          <td>${eq.equipment_type}</td>
          <td>${eq.location ?? '—'}</td>
          <td>${statusPill(eq.status)}</td>
          <td class="text-center">${workloadBadge(eq.workload_level ?? 'underutilized', utilization)}</td>
          <td class="text-end">${moneyFmt.format(eq.hourly_rate ?? 0)}</td>
        </tr>
      `);
    });
  }

  function renderProjects(rows) {
    const tbody = document.getElementById('projects-body');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No projects found.</td></tr>`;
      return;
    }
    rows.forEach((p) => {
      const progress = Number(p.avg_progress ?? 0).toFixed(0);
      tbody.insertAdjacentHTML('beforeend', `
        <tr>
          <td class="fw-semibold">${p.project_name}</td>
          <td>${statusPill(p.status)}</td>
          <td>${priorityChip(p.priority)}</td>
          <td><small class="text-muted">${p.start_date} → ${p.end_date}</small></td>
          <td class="text-center">
            <div class="progress" style="height: 8px;">
              <div class="progress-bar" role="progressbar" style="width: ${progress}%;" aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <small class="text-muted">${progress}%</small>
          </td>
          <td class="text-end">
            <div>${moneyFmt.format(p.budget_allocated ?? 0)}</div>
            <small class="text-muted">Spent ${moneyFmt.format(p.total_spent ?? 0)}</small>
          </td>
        </tr>
      `);
    });
  }

  function renderTasks(rows) {
    const tbody = document.getElementById('tasks-body');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No tasks found.</td></tr>`;
      return;
    }
    rows.forEach((t) => {
      const progress = Number(t.progress_percentage ?? 0).toFixed(0);
      tbody.insertAdjacentHTML('beforeend', `
        <tr>
          <td class="fw-semibold">${t.task_name}</td>
          <td>${t.project_name}</td>
          <td>${statusPill(t.status)}</td>
          <td>${priorityChip(t.priority)}</td>
          <td><small class="text-muted">${t.start_date} → ${t.end_date}</small></td>
          <td class="text-center">
            <div class="progress" style="height: 8px;">
              <div class="progress-bar bg-success" role="progressbar" style="width: ${progress}%;" aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <small class="text-muted">${progress}%</small>
          </td>
        </tr>
      `);
    });
  }

  async function loadSummary() {
    try {
      const data = await fetchJSON('summary.php');
      renderSummary(data);
    } catch (err) {
      console.error(err);
      showToast('Summary error', err.message, 'danger');
    }
  }

  async function loadAlerts() {
    try {
      const data = await fetchJSON('alerts.php');
      renderAlerts(data.alerts || []);
    } catch (err) {
      console.error(err);
      showToast('Alert load error', err.message, 'danger');
    }
  }

  async function loadEmployees(query = '') {
    try {
      const data = await fetchJSON(`employees.php${query ? `?q=${encodeURIComponent(query)}` : ''}`);
      employeesCache = data.employees || [];
      renderEmployees(employeesCache);
    } catch (err) {
      console.error(err);
      showToast('Employee load error', err.message, 'danger');
    }
  }

  async function loadEquipment(query = '') {
    try {
      const data = await fetchJSON(`equipment.php${query ? `?q=${encodeURIComponent(query)}` : ''}`);
      equipmentCache = data.equipment || [];
      renderEquipment(equipmentCache);
    } catch (err) {
      console.error(err);
      showToast('Equipment load error', err.message, 'danger');
    }
  }

  async function loadProjects() {
    try {
      const data = await fetchJSON('projects.php');
      projectsCache = data.projects || [];
      renderProjects(projectsCache);
      populateProjectSelect();
    } catch (err) {
      console.error(err);
      showToast('Project load error', err.message, 'danger');
    }
  }

  async function loadTasks(projectId = 0) {
    try {
      const query = projectId ? `?project_id=${projectId}` : '';
      const data = await fetchJSON(`tasks.php${query}`);
      tasksCache = data.tasks || [];
      renderTasks(tasksCache);
      populateTaskSelect(projectId);
    } catch (err) {
      console.error(err);
      showToast('Task load error', err.message, 'danger');
    }
  }

  async function loadBudgets(projectId = 0) {
    try {
      const query = projectId ? `?project_id=${projectId}` : '';
      const data = await fetchJSON(`budgets.php${query}`);
      budgetsCache = data.budgets || [];
    } catch (err) {
      console.error(err);
      showToast('Budget load error', err.message, 'danger');
    }
  }

  async function loadUtilization() {
    try {
      const data = await fetchJSON('utilization.php');
      renderUtilizationLists(data);
    } catch (err) {
      console.error(err);
      showToast('Utilization load error', err.message, 'danger');
    }
  }

  async function loadAssignmentOptions(projectId) {
    try {
      const data = await fetchJSON(`assignment_options.php${projectId ? `?project_id=${projectId}` : ''}`);
      if (projectId) {
        // overwrite tasks for this project in form only; table uses loadTasks
        populateTaskSelect(projectId, data.tasks || []);
        budgetsCache = data.budgets || [];
      }
      renderResourceOptions(data);
    } catch (err) {
      console.error(err);
      showToast('Assignment options error', err.message, 'danger');
    }
  }

  function findResourceName(type, id) {
    if (type === 'employee') {
      const match = employeesCache.find((e) => Number(e.employee_id ?? e.id) === Number(id));
      return match ? match.employee_name || match.name : `Employee #${id}`;
    }
    const match = equipmentCache.find((e) => Number(e.equipment_id ?? e.id) === Number(id));
    return match ? match.equipment_name || match.name : `Equipment #${id}`;
  }

  function renderUtilizationLists(data) {
    const underList = document.getElementById('underutilized-list');
    const overList = document.getElementById('overloaded-list');
    const latest = document.getElementById('utilization-latest');
    if (latest && data.latest_date) latest.textContent = `Latest snapshot: ${data.latest_date}`;
    if (!underList || !overList) return;

    const underItems = [
      ...(data.employees?.underutilized || []).map((e) => ({ type: 'employee', ...e })),
      ...(data.equipment?.underutilized || []).map((e) => ({ type: 'equipment', ...e })),
      ...(data.employees?.optimal || []).map((e) => ({ type: 'employee', ...e })),
      ...(data.equipment?.optimal || []).map((e) => ({ type: 'equipment', ...e })),
    ];
    const overItems = [
      ...(data.employees?.overloaded || []).map((e) => ({ type: 'employee', ...e })),
      ...(data.employees?.critical || []).map((e) => ({ type: 'employee', ...e })),
      ...(data.equipment?.overloaded || []).map((e) => ({ type: 'equipment', ...e })),
      ...(data.equipment?.critical || []).map((e) => ({ type: 'equipment', ...e })),
    ];

    underList.innerHTML = '';
    overList.innerHTML = '';

    if (!underItems.length) {
      underList.innerHTML = '<div class="list-group-item text-muted">No available capacity detected.</div>';
    } else {
      underItems.forEach((item) => {
        underList.insertAdjacentHTML(
          'beforeend',
          `<div class="list-group-item d-flex justify-content-between">
            <div>
              <strong>${findResourceName(item.type, item.resource_id)}</strong>
              <div class="text-muted">${item.type}</div>
            </div>
            <span class="utilization-pill util-underutilized">${percentFmt(item.utilization_percentage)} · ${item.workload_level}</span>
          </div>`
        );
      });
    }

    if (!overItems.length) {
      overList.innerHTML = '<div class="list-group-item text-muted">No overloaded resources.</div>';
    } else {
      overItems.forEach((item) => {
        overList.insertAdjacentHTML(
          'beforeend',
          `<div class="list-group-item d-flex justify-content-between">
            <div>
              <strong>${findResourceName(item.type, item.resource_id)}</strong>
              <div class="text-muted">${item.type}</div>
            </div>
            <span class="utilization-pill ${item.workload_level === 'critical' ? 'util-critical' : 'util-overloaded'}">${percentFmt(item.utilization_percentage)} · ${item.workload_level}</span>
          </div>`
        );
      });
    }
  }

  function bindAssignmentForm() {
    const projectSelect = document.getElementById('assign-project');
    const typeSelect = document.getElementById('assign-type');
    const resourceSelect = document.getElementById('assign-resource');
    const form = document.getElementById('assignment-form');
    const note = document.getElementById('assign-availability-note');
    const startInput = document.getElementById('assign-start');
    const endInput = document.getElementById('assign-end');

    if (startInput && endInput) {
      const today = new Date().toISOString().split('T')[0];
      if (!startInput.value) startInput.value = today;
      if (!endInput.value) endInput.value = today;
    }

    if (projectSelect) {
      projectSelect.addEventListener('change', (e) => {
        const projectId = Number(e.target.value);
        loadTasks(projectId);
        loadBudgets(projectId);
        loadAssignmentOptions(projectId);
        if (note) note.textContent = projectId ? 'Options filtered by selected project.' : 'Select a project to load tasks and resources.';
      });
    }

    if (typeSelect) {
      typeSelect.addEventListener('change', () => {
        const projectId = Number(projectSelect?.value || 0);
        loadAssignmentOptions(projectId);
      });
    }

    if (form) {
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        const projectId = Number(projectSelect?.value || 0);
        const taskId = Number(document.getElementById('assign-task')?.value || 0);
        const assignmentType = typeSelect?.value;
        const resourceId = Number(resourceSelect?.value || 0);
        const allocation_percentage = Number(document.getElementById('assign-percentage')?.value || 0);
        const allocated_hours = Number(document.getElementById('assign-hours')?.value || 0);
        const start_date = document.getElementById('assign-start')?.value;
        const end_date = document.getElementById('assign-end')?.value;
        const notes = document.getElementById('assign-notes')?.value || '';

        if (!projectId || !taskId || !assignmentType || !resourceId || !start_date || !end_date) {
          showToast('Missing info', 'Please fill required fields.', 'warning');
          return;
        }

        if (new Date(start_date) > new Date(end_date)) {
          showToast('Date error', 'Start date must be before end date.', 'warning');
          return;
        }

        submitAssignment({
          project_id: projectId,
          task_id: taskId,
          assignment_type: assignmentType,
          resource_id: resourceId,
          allocation_percentage,
          allocated_hours,
          start_date,
          end_date,
          notes,
        });
      });
    }
  }

  async function submitAssignment(payload) {
    try {
      await fetchJSON('assignments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      showToast('Assigned', 'Resource added to task', 'success');
      loadAlerts();
      loadTasks(payload.project_id);
      loadUtilization();
    } catch (err) {
      console.error(err);
      showToast('Assign failed', err.message, 'danger');
    }
  }

  function bindSearchInputs() {
    const employeeSearch = document.getElementById('employee-search');
    if (employeeSearch) {
      employeeSearch.addEventListener('input', (e) => loadEmployees(e.target.value));
    }

    const equipmentSearch = document.getElementById('equipment-search');
    if (equipmentSearch) {
      equipmentSearch.addEventListener('input', (e) => loadEquipment(e.target.value));
    }
  }

  function bindRefreshButtons() {
    const refreshBtn = document.getElementById('refresh-btn');
    const alertsRefresh = document.getElementById('alerts-refresh');
    const assignRefresh = document.getElementById('assign-refresh');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        showToast('Refreshing', 'Fetching the latest data...', 'info');
        loadAll();
      });
    }
    if (alertsRefresh) alertsRefresh.addEventListener('click', loadAlerts);
    if (assignRefresh) assignRefresh.addEventListener('click', () => {
      const projectSelect = document.getElementById('assign-project');
      const projectId = projectSelect ? Number(projectSelect.value) : 0;
      loadAssignmentOptions(projectId);
    });
  }

  function loadAll() {
    loadSummary();
    loadAlerts();
    loadEmployees();
    loadEquipment();
    loadProjects();
    loadTasks();
    loadBudgets();
    loadUtilization();
  }

  document.addEventListener('DOMContentLoaded', () => {
    bindSearchInputs();
    bindRefreshButtons();
    bindAssignmentForm();
    loadAll();
    setInterval(loadAlerts, 20000);
  });
})();
