(() => {
  const apiBase = 'api';
  const toastContainer = document.getElementById('toast-container');
  const seenAlertIds = new Set();

  const moneyFmt = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
  const percentFmt = (value) => `${Number(value ?? 0).toFixed(0)}%`;

  function showToast(title, message, variant = 'primary') {
    if (!toastContainer) return;
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
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
  }

  async function fetchJSON(endpoint) {
    const res = await fetch(`${apiBase}/${endpoint}`);
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

    const totalAlerts = (alerts?.critical ?? 0) + (alerts?.high ?? 0) + (alerts?.medium ?? 0) + (alerts?.low ?? 0);
    setText('alerts-total', totalAlerts);
    setText('alerts-critical', alerts?.critical ?? 0);
    setText('alerts-high', alerts?.high ?? 0);
    setText('alerts-medium', alerts?.medium ?? 0);
    setText('alerts-low', alerts?.low ?? 0);

    // Utilization chips near summary (re-use alerts badges if desired)
    if (utilization) {
      const utilSummary = `Under: ${utilization.underutilized} · Optimal: ${utilization.optimal} · Overloaded: ${utilization.overloaded} · Critical: ${utilization.critical}`;
      const badge = document.getElementById('utilization-summary');
      if (badge) badge.textContent = utilSummary;
    }

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
      renderEmployees(data.employees || []);
    } catch (err) {
      console.error(err);
      showToast('Employee load error', err.message, 'danger');
    }
  }

  async function loadEquipment(query = '') {
    try {
      const data = await fetchJSON(`equipment.php${query ? `?q=${encodeURIComponent(query)}` : ''}`);
      renderEquipment(data.equipment || []);
    } catch (err) {
      console.error(err);
      showToast('Equipment load error', err.message, 'danger');
    }
  }

  async function loadProjects() {
    try {
      const data = await fetchJSON('projects.php');
      renderProjects(data.projects || []);
    } catch (err) {
      console.error(err);
      showToast('Project load error', err.message, 'danger');
    }
  }

  async function loadTasks() {
    try {
      const data = await fetchJSON('tasks.php');
      renderTasks(data.tasks || []);
    } catch (err) {
      console.error(err);
      showToast('Task load error', err.message, 'danger');
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
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        showToast('Refreshing', 'Fetching the latest data...', 'info');
        loadAll();
      });
    }
    if (alertsRefresh) alertsRefresh.addEventListener('click', loadAlerts);
  }

  function loadAll() {
    loadSummary();
    loadAlerts();
    loadEmployees();
    loadEquipment();
    loadProjects();
    loadTasks();
  }

  document.addEventListener('DOMContentLoaded', () => {
    bindSearchInputs();
    bindRefreshButtons();
    loadAll();
    setInterval(loadAlerts, 20000);
  });
})();
