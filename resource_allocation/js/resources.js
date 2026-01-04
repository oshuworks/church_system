(() => {
  const apiBase = 'api';
  const toastContainer = document.getElementById('toast-container');
  const seenAlertIds = new Set();
  const toastQueue = [];
  let isShowingToast = false;

  const moneyFmt = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PHP' });
  const percentFmt = (value) => `${Number(value ?? 0).toFixed(0)}%`;
  
  // Format date for Philippines timezone
  function formatDatePH(dateString) {
    if (!dateString || dateString === '0000-00-00') return 'Not set';
    const date = new Date(dateString + 'T00:00:00+08:00'); // Set to PH timezone
    return date.toLocaleDateString('en-PH', { 
      month: 'short', 
      day: 'numeric', 
      year: 'numeric' 
    });
  }
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

    // Utilization summary
    const under = Number(utilization?.underutilized ?? 0);
    const optimal = Number(utilization?.optimal ?? 0);
    const over = Number(utilization?.overloaded ?? 0);
    const critical = Number(utilization?.critical ?? 0);
    const totalUtilization = under + optimal + over + critical;
    setText('utilization-total', totalUtilization);
    setText('utilization-under', under);
    setText('utilization-optimal', optimal);
    setText('utilization-over', over);
    setText('utilization-critical', critical);


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


  function renderEmployees(rows) {
    const tbody = document.getElementById('employees-body');
    const countEl = document.getElementById('employees-count');
    if (!tbody) return;
    
    // Store full data for pagination
    window.employeesData = rows || [];
    
    // Initialize pagination
    if (!window.employeesPagination) {
      window.employeesPagination = { page: 1, pageSize: 5 };
    }
    
    // Render paginated data
    renderPaginatedTable('employees', window.employeesData, window.employeesPagination, renderEmployeeRow);
    
    // Update count
    if (countEl) countEl.textContent = `${window.employeesData.length} employees`;
    
    // Bind pagination controls
    bindTablePagination('employees');
  }

  function renderEmployeeRow(employee) {
    const utilization = employee.latest_utilization ?? employee.utilization_percentage;
    const assignments = employee.current_assignments || 0;
    const projects = employee.assigned_projects || 'None';
    
    return `
      <tr>
        <td>
          <div class="fw-semibold">${employee.employee_name}</div>
          <div class="text-muted small">${employee.email}</div>
        </td>
        <td>${employee.role}</td>
        <td>${employee.department ?? '—'}</td>
        <td>${statusPill(employee.status)}</td>
        <td class="text-center">
          <div class="fw-semibold">${assignments}</div>
          <div class="text-muted small">${projects.length > 20 ? projects.substring(0, 20) + '...' : projects}</div>
        </td>
        <td class="text-center">${workloadBadge(employee.workload_level ?? 'underutilized', utilization)}</td>
        <td class="text-end">${moneyFmt.format(employee.hourly_rate ?? 0)}</td>
      </tr>
    `;
  }

  function renderEquipment(rows) {
    const tbody = document.getElementById('equipment-body');
    const countEl = document.getElementById('equipment-count');
    if (!tbody) return;
    
    // Store full data for pagination
    window.equipmentData = rows || [];
    
    // Initialize pagination
    if (!window.equipmentPagination) {
      window.equipmentPagination = { page: 1, pageSize: 5 };
    }
    
    // Render paginated data
    renderPaginatedTable('equipment', window.equipmentData, window.equipmentPagination, renderEquipmentRow);
    
    // Update count
    if (countEl) countEl.textContent = `${window.equipmentData.length} equipment`;
    
    // Bind pagination controls
    bindTablePagination('equipment');
  }

  function renderEquipmentRow(eq) {
    const utilization = eq.latest_utilization ?? eq.utilization_percentage;
    const assignments = eq.current_assignments || 0;
    const projects = eq.assigned_projects || 'None';
    
    return `
      <tr>
        <td class="fw-semibold">${eq.equipment_name}</td>
        <td>${eq.equipment_type}</td>
        <td>${eq.location ?? '—'}</td>
        <td>${statusPill(eq.status)}</td>
        <td class="text-center">
          <div class="fw-semibold">${assignments}</div>
          <div class="text-muted small">${projects.length > 20 ? projects.substring(0, 20) + '...' : projects}</div>
        </td>
        <td class="text-center">${workloadBadge(eq.workload_level ?? 'underutilized', utilization)}</td>
        <td class="text-end">${moneyFmt.format(eq.hourly_rate ?? 0)}</td>
      </tr>
    `;
  }

  function renderProjects(rows) {
    const tbody = document.getElementById('projects-body');
    const countEl = document.getElementById('projects-count');
    if (!tbody) return;
    
    // Store full data for pagination
    window.projectsData = rows || [];
    
    // Initialize pagination
    if (!window.projectsPagination) {
      window.projectsPagination = { page: 1, pageSize: 5 };
    }
    
    // Render paginated data
    renderPaginatedTable('projects', window.projectsData, window.projectsPagination, renderProjectRow);
    
    // Update count
    if (countEl) countEl.textContent = `${window.projectsData.length} projects`;
    
    // Bind pagination controls
    bindTablePagination('projects');
  }

  function renderProjectRow(p) {
    const progress = Number(p.avg_progress ?? 0).toFixed(0);
    const totalAssignments = p.total_assignments || 0;
    const employees = p.employees_assigned || 0;
    const equipment = p.equipment_assigned || 0;
    const budgets = p.budgets_assigned || 0;
    
    let resourceSummary = '';
    if (totalAssignments > 0) {
      const parts = [];
      if (employees > 0) parts.push(`${employees} 👤`);
      if (equipment > 0) parts.push(`${equipment} 🛠️`);
      if (budgets > 0) parts.push(`${budgets} 💰`);
      resourceSummary = parts.join(' · ');
    } else {
      resourceSummary = 'None';
    }
    
    return `
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
        <td class="text-center">
          <div class="fw-semibold">${totalAssignments}</div>
          <div class="text-muted small">${resourceSummary}</div>
        </td>
        <td class="text-end">
          <div>${moneyFmt.format(p.budget_allocated ?? 0)}</div>
          <small class="text-muted">Spent ${moneyFmt.format(p.total_spent ?? 0)}</small>
        </td>
      </tr>
    `;
  }

  function renderTasks(rows) {
    const tbody = document.getElementById('tasks-body');
    const countEl = document.getElementById('tasks-count');
    if (!tbody) return;
    
    // Store full data for pagination
    window.tasksData = rows || [];
    
    // Initialize pagination
    if (!window.tasksPagination) {
      window.tasksPagination = { page: 1, pageSize: 5 };
    }
    
    // Render paginated data
    renderPaginatedTable('tasks', window.tasksData, window.tasksPagination, renderTaskRow);
    
    // Update count
    if (countEl) countEl.textContent = `${window.tasksData.length} tasks`;
    
    // Bind pagination controls
    bindTablePagination('tasks');
  }

  function renderTaskRow(t) {
    const progress = Number(t.progress_percentage ?? 0).toFixed(0);
    const assignedCount = t.assigned_count || 0;
    const assignedResources = t.assigned_resources || 'None';
    
    return `
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
        <td class="text-center">
          <div class="fw-semibold">${assignedCount}</div>
          <div class="text-muted small">${assignedResources.length > 30 ? assignedResources.substring(0, 30) + '...' : assignedResources}</div>
        </td>
      </tr>
    `;
  }

  // Generic pagination functions for tables
  function renderPaginatedTable(tableName, data, pagination, renderRowFunction) {
    // Assignments table uses a different ID structure
    const tbodyId = tableName === 'assignments' ? 'assignments-list' : `${tableName}-body`;
    const tbody = document.getElementById(tbodyId);
    if (!tbody || !data) return;

    console.log(`Rendering ${tableName} table:`, { data, pagination }); // Debug log

    const startIndex = (pagination.page - 1) * pagination.pageSize;
    const endIndex = startIndex + pagination.pageSize;
    const pageItems = data.slice(startIndex, endIndex);

    console.log(`Page items for ${tableName}:`, pageItems); // Debug log

    tbody.innerHTML = '';
    
    if (!pageItems.length) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4">No ${tableName} found.</td></tr>`;
      return;
    }

    pageItems.forEach(item => {
      tbody.insertAdjacentHTML('beforeend', renderRowFunction(item));
    });

    // Update pagination buttons
    updateTablePaginationButtons(tableName, data, pagination);
  }

  function updateTablePaginationButtons(tableName, data, pagination) {
    const totalPages = Math.ceil(data.length / pagination.pageSize);
    const prevBtn = document.getElementById(`${tableName}-prev`);
    const nextBtn = document.getElementById(`${tableName}-next`);
    
    if (prevBtn) prevBtn.disabled = pagination.page <= 1;
    if (nextBtn) nextBtn.disabled = pagination.page >= totalPages;
  }

  function bindTablePagination(tableName) {
    const prevBtn = document.getElementById(`${tableName}-prev`);
    const nextBtn = document.getElementById(`${tableName}-next`);
    const pagination = window[`${tableName}Pagination`];
    const data = window[`${tableName}Data`];
    
    if (prevBtn) {
      prevBtn.onclick = () => {
        if (pagination.page > 1) {
          pagination.page--;
          // Call the appropriate render function based on table name
          switch(tableName) {
            case 'employees':
              renderPaginatedTable('employees', data, pagination, renderEmployeeRow);
              break;
            case 'equipment':
              renderPaginatedTable('equipment', data, pagination, renderEquipmentRow);
              break;
            case 'projects':
              renderPaginatedTable('projects', data, pagination, renderProjectRow);
              break;
            case 'tasks':
              renderPaginatedTable('tasks', data, pagination, renderTaskRow);
              break;
            case 'assignments':
              renderPaginatedTable('assignments', data, pagination, renderAssignmentRow);
              break;
          }
        }
      };
    }
    
    if (nextBtn) {
      nextBtn.onclick = () => {
        const totalPages = Math.ceil(data.length / pagination.pageSize);
        if (pagination.page < totalPages) {
          pagination.page++;
          // Call the appropriate render function based on table name
          switch(tableName) {
            case 'employees':
              renderPaginatedTable('employees', data, pagination, renderEmployeeRow);
              break;
            case 'equipment':
              renderPaginatedTable('equipment', data, pagination, renderEquipmentRow);
              break;
            case 'projects':
              renderPaginatedTable('projects', data, pagination, renderProjectRow);
              break;
            case 'tasks':
              renderPaginatedTable('tasks', data, pagination, renderTaskRow);
              break;
            case 'assignments':
              renderPaginatedTable('assignments', data, pagination, renderAssignmentRow);
              break;
          }
        }
      };
    }
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


  async function loadAssignments(filter = 'all', status = 'assigned') {
    try {
      const queryParams = new URLSearchParams();
      if (filter !== 'all') queryParams.append('assignment_type', filter);
      if (status !== 'assigned') queryParams.append('status', status);
      
      const data = await fetchJSON(`assignments.php${queryParams.toString() ? '?' + queryParams.toString() : ''}`);
      renderAssignments(data.assignments || []);
    } catch (err) {
      console.error(err);
      showToast('Assignments load error', err.message, 'danger');
    }
  }

  function renderAssignments(assignments) {
    const tbody = document.getElementById('assignments-list');
    const countEl = document.getElementById('assignments-count');
    if (!tbody) return;
    
    console.log('Assignments data:', assignments); // Debug log
    
    // Store full data for pagination
    window.assignmentsData = assignments || [];
    
    // Initialize pagination
    if (!window.assignmentsPagination) {
      window.assignmentsPagination = { page: 1, pageSize: 5 };
    }
    
    // Render paginated data
    renderPaginatedTable('assignments', window.assignmentsData, window.assignmentsPagination, renderAssignmentRow);
    
    // Update count
    if (countEl) countEl.textContent = `${window.assignmentsData.length} assignments`;
    
    // Bind pagination controls
    bindTablePagination('assignments');
  }

  function renderAssignmentRow(assignment) {
    const allocationPercent = percentFmt(assignment.allocation_percentage);
    const usedHours = Number(assignment.calculated_hours_used || assignment.actual_hours_used || 0).toFixed(2);
    const allocatedHours = Number(assignment.allocated_hours || 0).toFixed(2);
    const hoursText = `${allocatedHours}h / ${usedHours}h used`;
    
    // Format dates for Philippines timezone
    const startDate = formatDatePH(assignment.start_date);
    const endDate = formatDatePH(assignment.end_date);
    const datesText = `${startDate} → ${endDate}`;
    
    const isAssigned = assignment.status === 'assigned';
    
    return `
      <tr>
        <td>
          <div class="fw-semibold">${assignment.resource_name}</div>
          <div class="text-muted small">ID: ${assignment.resource_id}</div>
        </td>
        <td>
          <span class="badge bg-light text-dark">${assignment.assignment_type}</span>
        </td>
        <td>
          <div class="fw-semibold">${assignment.project_name}</div>
          <div class="text-muted small">ID: ${assignment.project_id}</div>
        </td>
        <td>
          <div class="fw-semibold">${assignment.task_name}</div>
          <div class="text-muted small">ID: ${assignment.task_id}</div>
        </td>
        <td class="text-center">
          <div class="fw-semibold">${allocationPercent}</div>
        </td>
        <td class="text-center">
          <div class="small">${hoursText}</div>
        </td>
        <td>
          <div class="small">${datesText}</div>
        </td>
        <td>${statusPill(assignment.status)}</td>
        <td>
          ${isAssigned ? `
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-success" onclick="completeAssignment(${assignment.assignment_id})" title="Complete">
                <i class="fas fa-check"></i>
              </button>
              <button class="btn btn-outline-danger" onclick="cancelAssignment(${assignment.assignment_id})" title="Cancel">
                <i class="fas fa-times"></i>
              </button>
            </div>
          ` : `
            <span class="text-muted small">No actions</span>
          `}
        </td>
      </tr>
    `;
  }

  function bindAssignmentControls() {
    const filterSelect = document.getElementById('assignment-filter');
    const statusSelect = document.getElementById('assignment-status');
    const refreshBtn = document.getElementById('assignments-refresh');

    if (filterSelect) {
      filterSelect.addEventListener('change', () => {
        const filter = filterSelect.value;
        const status = statusSelect?.value || 'assigned';
        loadAssignments(filter, status);
      });
    }

    if (statusSelect) {
      statusSelect.addEventListener('change', () => {
        const filter = filterSelect?.value || 'all';
        const status = statusSelect.value;
        loadAssignments(filter, status);
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        const filter = filterSelect?.value || 'all';
        const status = statusSelect?.value || 'assigned';
        loadAssignments(filter, status);
      });
    }
  }

  async function completeAssignment(assignmentId) {
    if (confirm('Are you sure you want to mark this assignment as completed?')) {
      try {
        await fetchJSON(`assignments.php?id=${assignmentId}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'complete' })
        });
        showToast('Assignment Completed', `Assignment ID: ${assignmentId}`, 'success');
        // Reload assignments to reflect the change
        const filterSelect = document.getElementById('assignment-filter');
        const statusSelect = document.getElementById('assignment-status');
        const filter = filterSelect?.value || 'all';
        const status = statusSelect?.value || 'assigned';
        loadAssignments(filter, status);
      } catch (err) {
        console.error(err);
        showToast('Complete failed', err.message, 'danger');
      }
    }
  }

  async function cancelAssignment(assignmentId) {
    if (confirm('Are you sure you want to cancel this assignment?')) {
      try {
        await fetchJSON(`assignments.php?id=${assignmentId}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'cancel' })
        });
        showToast('Assignment Cancelled', `Assignment ID: ${assignmentId}`, 'warning');
        // Reload assignments to reflect the change
        const filterSelect = document.getElementById('assignment-filter');
        const statusSelect = document.getElementById('assignment-status');
        const filter = filterSelect?.value || 'all';
        const status = statusSelect?.value || 'assigned';
        loadAssignments(filter, status);
      } catch (err) {
        console.error(err);
        showToast('Cancel failed', err.message, 'danger');
      }
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
    const underCount = document.getElementById('underutilized-count');
    const overCount = document.getElementById('overloaded-count');
    
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

    // Store full lists for pagination
    window.utilizationData = {
      underutilized: underItems,
      overloaded: overItems
    };

    // Initialize pagination state
    if (!window.utilizationPagination) {
      window.utilizationPagination = {
        underutilized: { page: 1, pageSize: 5 },
        overloaded: { page: 1, pageSize: 5 }
      };
    }

    // Render paginated lists
    renderPaginatedList('underutilized');
    renderPaginatedList('overloaded');
    
    // Update counts
    if (underCount) underCount.textContent = `${underItems.length} items`;
    if (overCount) overCount.textContent = `${overItems.length} items`;
    
    // Bind pagination controls
    bindUtilizationPagination();
  }

  function renderPaginatedList(type) {
    const list = document.getElementById(`${type}-list`);
    const pagination = window.utilizationPagination[type];
    const items = window.utilizationData[type];
    
    if (!list || !items) return;

    const startIndex = (pagination.page - 1) * pagination.pageSize;
    const endIndex = startIndex + pagination.pageSize;
    const pageItems = items.slice(startIndex, endIndex);

    list.innerHTML = '';
    
    if (!pageItems.length) {
      list.innerHTML = '<div class="list-group-item text-muted">No items found.</div>';
      return;
    }

    pageItems.forEach((item) => {
      list.insertAdjacentHTML(
        'beforeend',
        `<div class="list-group-item d-flex justify-content-between">
          <div>
            <strong>${findResourceName(item.type, item.resource_id)}</strong>
            <div class="text-muted">${item.type}</div>
          </div>
          <span class="utilization-pill ${item.workload_level === 'critical' ? 'util-critical' : item.workload_level === 'overloaded' ? 'util-overloaded' : item.workload_level === 'optimal' ? 'util-optimal' : 'util-underutilized'}">${percentFmt(item.utilization_percentage)} · ${item.workload_level}</span>
        </div>`
      );
    });

    // Update pagination buttons
    updatePaginationButtons(type);
  }

  function updatePaginationButtons(type) {
    const pagination = window.utilizationPagination[type];
    const items = window.utilizationData[type];
    const totalPages = Math.ceil(items.length / pagination.pageSize);
    
    const prevBtn = document.getElementById(`${type}-prev`);
    const nextBtn = document.getElementById(`${type}-next`);
    
    if (prevBtn) prevBtn.disabled = pagination.page <= 1;
    if (nextBtn) nextBtn.disabled = pagination.page >= totalPages;
  }

  function bindUtilizationPagination() {
    ['underutilized', 'overloaded'].forEach(type => {
      const prevBtn = document.getElementById(`${type}-prev`);
      const nextBtn = document.getElementById(`${type}-next`);
      
      if (prevBtn) {
        prevBtn.onclick = () => {
          if (window.utilizationPagination[type].page > 1) {
            window.utilizationPagination[type].page--;
            renderPaginatedList(type);
          }
        };
      }
      
      if (nextBtn) {
        nextBtn.onclick = () => {
          const pagination = window.utilizationPagination[type];
          const items = window.utilizationData[type];
          const totalPages = Math.ceil(items.length / pagination.pageSize);
          
          if (pagination.page < totalPages) {
            pagination.page++;
            renderPaginatedList(type);
          }
        };
      }
    });
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

        // Additional client-side validation
        const allocationPercentage = Number(document.getElementById('assign-percentage')?.value || 0);
        const allocatedHours = Number(document.getElementById('assign-hours')?.value || 0);
        
        if (allocationPercentage < 1 || allocationPercentage > 150) {
          showToast('Invalid allocation', 'Allocation percentage must be between 1 and 150', 'warning');
          return;
        }
        
        if (allocatedHours < 0.1) {
          showToast('Invalid hours', 'Allocated hours must be at least 0.1', 'warning');
          return;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const startDateObj = new Date(start_date);
        
        if (startDateObj < today) {
          showToast('Date error', 'Start date cannot be in the past', 'warning');
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
    const assignRefresh = document.getElementById('assign-refresh');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        showToast('Refreshing', 'Fetching the latest data...', 'info');
        loadAll();
      });
    }
    if (assignRefresh) assignRefresh.addEventListener('click', () => {
      const projectSelect = document.getElementById('assign-project');
      const projectId = projectSelect ? Number(projectSelect.value) : 0;
      loadAssignmentOptions(projectId);
    });
  }

  function loadAll() {
    loadSummary();
    loadEmployees();
    loadEquipment();
    loadProjects();
    loadTasks();
    loadBudgets();
    loadUtilization();
    loadAssignments();
  }

  // Auto-refresh assignments for real-time updates
  function startRealTimeUpdates() {
    // Refresh assignments every 30 seconds for real-time hour tracking
    setInterval(() => {
      const filterSelect = document.getElementById('assignment-filter');
      const statusSelect = document.getElementById('assignment-status');
      const filter = filterSelect?.value || 'all';
      const status = statusSelect?.value || 'assigned';
      loadAssignments(filter, status);
    }, 30000); // 30 seconds
  }

  document.addEventListener('DOMContentLoaded', () => {
    bindSearchInputs();
    bindRefreshButtons();
    bindAssignmentForm();
    bindAssignmentControls();
    loadAll();
    startRealTimeUpdates(); // Start real-time updates
  });

  // Expose functions globally for onclick handlers
  window.completeAssignment = completeAssignment;
  window.cancelAssignment = cancelAssignment;
})();
