# Project Planning and Scheduling Module

## Module Overview
This module provides comprehensive project planning and scheduling functionality for the iServe system. It includes project management, Work Breakdown Structure (WBS), Gantt charts, and timeline visualization.

## Module Structure
```
modules/project_planning/
├── api/                    # API endpoints
│   ├── projects.php       # GET all projects
│   ├── project.php        # CRUD for single project
│   ├── task.php           # CRUD for single task
│   └── tasks.php          # GET all tasks
├── js/                     # JavaScript files
│   └── gantt.js           # Gantt chart functionality
├── css/                    # Stylesheets
│   └── style.css          # Module-specific styles
├── sql/                    # Database schema
│   └── schema.sql         # Projects and tasks tables
├── index.php              # Project listing page
├── project-details.php    # Project detail page with WBS, Gantt, Timeline
└── README.md              # This file
```

## Features

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

## Sub-Functions

### a. Define Project Timelines and Phases
- **Files**: `api/projects.php`, `api/project.php`
- **Tables**: `projects` table
- **Features**: 
  - Set project start and end dates
  - Define project phases through tasks
  - Track project duration

### b. Create WBS (Work Breakdown Structure)
- **Files**: `api/task.php`, `project-details.php`
- **Tables**: `tasks` table
- **Features**:
  - Create hierarchical task structure
  - Organize tasks by project
  - View tasks in table format

### c. Assign Start/End Dates
- **Files**: `api/task.php`
- **Tables**: `tasks` table (start_date, end_date columns)
- **Features**:
  - Set individual task dates
  - Validate date ranges
  - Track task duration

### d. Set Dependencies, Track Deadlines
- **Files**: `api/task.php`, `js/gantt.js`
- **Tables**: `tasks` table (dependency_task_id column)
- **Features**:
  - Define task dependencies
  - Prevent circular dependencies
  - Visualize dependencies in Gantt chart
  - Track deadlines and overdue tasks

## Database Tables

### projects
- `project_id` (INT, PRIMARY KEY)
- `name` (VARCHAR(255))
- `description` (TEXT)
- `start_date` (DATE)
- `end_date` (DATE)
- `budget` (DECIMAL(15,2))
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

### tasks
- `task_id` (INT, PRIMARY KEY)
- `project_id` (INT, FOREIGN KEY)
- `name` (VARCHAR(255))
- `description` (TEXT)
- `start_date` (DATE)
- `end_date` (DATE)
- `dependency_task_id` (INT, FOREIGN KEY, NULLABLE)
- `progress_percent` (INT, DEFAULT 0)

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

## File Descriptions

### API Files
- **projects.php**: Handles GET requests for all projects with aggregated statistics
- **project.php**: Handles CRUD operations for individual projects
- **task.php**: Handles CRUD operations for individual tasks with dependency validation
- **tasks.php**: Handles GET and POST requests for task collections

### Frontend Files
- **index.php**: Main project listing page with create/edit functionality and analytics
- **project-details.php**: Comprehensive project detail page with three views:
  - Work Breakdown Structure (WBS)
  - Gantt Chart
  - Timeline

### JavaScript Files
- **gantt.js**: Gantt chart initialization, interaction handlers, and utility functions

### CSS Files
- **style.css**: Module-specific styles for Gantt charts, timelines, and WBS tables

### SQL Files
- **schema.sql**: Database schema for projects and tasks tables only

## Usage

### Accessing the Module
Navigate to: `modules/project_planning/index.php`

### Creating a Project
1. Click "Create Project" button
2. Fill in project details (name, description, dates, budget)
3. Click "Create Project"

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

## Security Features
- SQL injection prevention using prepared statements
- Input validation for dates and dependencies
- Circular dependency prevention
- Foreign key constraints in database
- XSS prevention through HTML escaping

## Dependencies
- Bootstrap 5.3.0
- Font Awesome 6.4.0
- Frappe Gantt 0.6.1
- PHP 7.4+ with MySQLi extension
- MySQL 5.7+ or MariaDB 10.2+

## Color Scheme
The module uses a consistent color scheme:
- **Blue (#007bff)**: Primary actions, completed tasks, active states
- **Black (#000000)**: In-progress tasks, warnings, text
- **White (#ffffff)**: Backgrounds, text on colored backgrounds
- **Gray (#6c757d)**: Neutral states, borders

## Notes
- Tasks with dependencies cannot be deleted if other tasks depend on them
- Projects with tasks cannot be deleted (tasks must be deleted first)
- Task dates are validated to ensure start date is before end date
- Dependencies must belong to the same project
- All API endpoints return JSON responses
- The module is self-contained and can be used independently

## Integration
This module integrates with the main iServe system through:
- Shared database connection (`../../includes/db.php`)
- Shared navigation bar (`../../includes/navbar.php`)
- Shared CSS theme (`../../css/resources.css`)

## Reporting
This module is designed for separate reporting and can be presented independently as it contains all necessary files for Project Planning and Scheduling functionality.

