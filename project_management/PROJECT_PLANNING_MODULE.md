# Project Planning and Scheduling Module - iServe

## Overview
This module provides comprehensive project planning and scheduling functionality for the iServe system, including project management, Work Breakdown Structure (WBS), Gantt charts, and timeline visualization.

## Features Implemented

### 1. Project Management
- **Create Projects**: Full CRUD operations for projects
- **Project Details**: View project information with progress tracking
- **Project Analytics**: Dashboard with project statistics
- **Project Search**: Filter projects by name or description

### 2. Work Breakdown Structure (WBS)
- **Task Creation**: Create tasks with descriptions, dates, and dependencies
- **Task Management**: Edit, update progress, and delete tasks
- **Dependency Management**: Set task dependencies to create project workflows
- **Progress Tracking**: Visual progress bars and percentage tracking

### 3. Gantt Chart Visualization
- **Interactive Gantt Chart**: Visual timeline using Frappe Gantt library
- **Task Dependencies**: Visual representation of task dependencies
- **Date Management**: Drag-and-drop date changes (via API)
- **Progress Visualization**: Color-coded tasks by status (completed, in-progress, not-started)
- **View Modes**: Day, Week, and Month views

### 4. Timeline View
- **Chronological Timeline**: Visual timeline of all project tasks
- **Status Indicators**: Color-coded markers for task status
- **Overdue Alerts**: Visual indicators for overdue tasks
- **Task Details**: Quick access to task information

## Files Created/Modified

### API Endpoints
1. **`/api/projects.php`** - GET all projects with statistics
2. **`/api/project.php`** - CRUD operations for single project
3. **`/api/task.php`** - CRUD operations for single task with dependency validation
4. **`/api/tasks.php`** - GET all tasks (enhanced with dependency info)

### Frontend Pages
1. **`index.php`** - Project listing page with create/edit functionality
2. **`project-details.php`** - Comprehensive project detail page with WBS, Gantt, and Timeline views

### JavaScript Files
1. **`js/gantt.js`** - Gantt chart initialization and interaction handlers
2. **`js/main.js`** - Enhanced with project planning UI interactions (existing file)

### CSS Files
1. **`css/style.css`** - Enhanced with Gantt chart and timeline styling

### Database Tables
- **`projects`** - Stores project information
- **`tasks`** - Stores task information with dependencies

## Usage

### Creating a Project
1. Navigate to the Projects page
2. Click "Create Project" button
3. Fill in project details (name, description, dates, budget)
4. Click "Create Project"

### Adding Tasks
1. Open a project from the project list
2. Go to the "Work Breakdown Structure" tab
3. Click "Add Task"
4. Fill in task details:
   - Task name (required)
   - Description
   - Start and end dates
   - Dependency (optional - select a task that must complete first)
   - Initial progress percentage
5. Click "Save Task"

### Viewing Gantt Chart
1. Open a project
2. Click the "Gantt Chart" tab
3. Use view mode buttons (Day/Week/Month) to change the timeline scale
4. Click on tasks to edit them

### Viewing Timeline
1. Open a project
2. Click the "Timeline" tab
3. View all tasks in chronological order
4. Click "Edit" on any task to modify it

## API Endpoints

### Projects
- `GET /api/projects.php` - Get all projects with statistics
- `GET /api/project.php?project_id=X` - Get single project with tasks
- `POST /api/project.php` - Create new project
- `PUT /api/project.php` - Update project
- `DELETE /api/project.php` - Delete project

### Tasks
- `GET /api/task.php?task_id=X` - Get single task
- `GET /api/tasks.php?project_id=X` - Get all tasks for a project
- `POST /api/task.php` - Create new task
- `PUT /api/task.php` - Update task
- `DELETE /api/task.php` - Delete task

## Security Features
- SQL injection prevention using prepared statements
- Input validation for dates and dependencies
- Circular dependency prevention
- Foreign key constraints in database

## Dependencies
- Bootstrap 5.3.0
- Font Awesome 6.4.0
- Frappe Gantt 0.6.1

## Notes
- Tasks with dependencies cannot be deleted if other tasks depend on them
- Projects with tasks cannot be deleted (tasks must be deleted first)
- Task dates are validated to ensure start date is before end date
- Dependencies must belong to the same project

