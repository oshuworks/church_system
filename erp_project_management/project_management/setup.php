<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - iServe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f6fa;
            padding: 20px;
        }
        .setup-card {
            max-width: 800px;
            margin: 50px auto;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card setup-card">
            <div class="card-header bg-primary text-white">
                <h3><i class="fas fa-database"></i> Database Setup - iServe</h3>
            </div>
            <div class="card-body">
                <?php
                $servername = "localhost";
                $username = "root";
                $password = "";
                $dbname = "erp_project_management";
                
                $errors = [];
                $success = [];
                
                // Step 1: Connect to MySQL
                $conn_temp = new mysqli($servername, $username, $password);
                if ($conn_temp->connect_error) {
                    $errors[] = "Failed to connect to MySQL: " . $conn_temp->connect_error;
                } else {
                    $success[] = "✓ Connected to MySQL server";
                    
                    // Step 2: Create database
                    $sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                    if ($conn_temp->query($sql)) {
                        $success[] = "✓ Database '$dbname' created or already exists";
                    } else {
                        $errors[] = "Error creating database: " . $conn_temp->error;
                    }
                    
                    $conn_temp->close();
                    
                    // Step 3: Connect to database
                    $conn = new mysqli($servername, $username, $password, $dbname);
                    if ($conn->connect_error) {
                        $errors[] = "Failed to connect to database: " . $conn->connect_error;
                    } else {
                        $success[] = "✓ Connected to database '$dbname'";
                        $conn->set_charset("utf8mb4");
                        
                        // Step 4: Read and execute SQL file
                        $sql_file = __DIR__ . '/sql/database.sql';
                        if (file_exists($sql_file)) {
                            $sql_content = file_get_contents($sql_file);
                            
                            // Remove CREATE DATABASE and USE statements (already done)
                            $sql_content = preg_replace('/CREATE DATABASE.*?;/i', '', $sql_content);
                            $sql_content = preg_replace('/USE.*?;/i', '', $sql_content);
                            
                            // Split by semicolon and execute each statement
                            $statements = array_filter(array_map('trim', explode(';', $sql_content)));
                            
                            $tables_created = 0;
                            $data_inserted = 0;
                            
                            foreach ($statements as $statement) {
                                if (empty($statement)) continue;
                                
                                // Skip INSERT statements for now (we'll do them separately)
                                if (stripos($statement, 'INSERT INTO') !== false) {
                                    if ($conn->query($statement)) {
                                        $data_inserted++;
                                    }
                                    continue;
                                }
                                
                                // Execute CREATE TABLE statements
                                if (stripos($statement, 'CREATE TABLE') !== false) {
                                    if ($conn->query($statement)) {
                                        $tables_created++;
                                    } else {
                                        // Check if table already exists
                                        if (strpos($conn->error, 'already exists') === false) {
                                            $errors[] = "Error creating table: " . $conn->error;
                                        }
                                    }
                                }
                            }
                            
                            if ($tables_created > 0) {
                                $success[] = "✓ Created $tables_created table(s)";
                            }
                            
                            if ($data_inserted > 0) {
                                $success[] = "✓ Inserted sample data ($data_inserted records)";
                            }
                            
                            // Check existing tables
                            $result = $conn->query("SHOW TABLES");
                            $existing_tables = [];
                            if ($result) {
                                while ($row = $result->fetch_array()) {
                                    $existing_tables[] = $row[0];
                                }
                                if (count($existing_tables) > 0) {
                                    $success[] = "✓ Found " . count($existing_tables) . " existing table(s): " . implode(', ', $existing_tables);
                                }
                            }
                            
                        } else {
                            $errors[] = "SQL file not found: $sql_file";
                        }
                        
                        $conn->close();
                    }
                }
                
                // Display results
                if (count($errors) > 0) {
                    echo '<div class="alert alert-danger">';
                    echo '<h5><i class="fas fa-exclamation-triangle me-2"></i>Errors:</h5><ul>';
                    foreach ($errors as $error) {
                        echo '<li class="error">' . htmlspecialchars($error) . '</li>';
                    }
                    echo '</ul></div>';
                }
                
                if (count($success) > 0) {
                    echo '<div class="alert alert-success">';
                    echo '<h5><i class="fas fa-info-circle me-2"></i>Setup Status:</h5><ul>';
                    foreach ($success as $msg) {
                        echo '<li class="success">' . htmlspecialchars($msg) . '</li>';
                    }
                    echo '</ul></div>';
                }
                
                if (count($errors) == 0) {
                    echo '<div class="alert alert-info">';
                    echo '<h5><i class="fas fa-check-circle me-2"></i>Setup Complete!</h5>';
                    echo '<p>Your database has been set up successfully. You can now use the application.</p>';
                    echo '<a href="' . ($BASE_URL ?? '') . '/index.php" class="btn btn-primary">Go to Application</a>'; 
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-warning">';
                    echo '<p>There were some errors during setup. Please check the error messages above.</p>';
                    echo '<p>You can try running this setup again, or manually execute the SQL file in phpMyAdmin.</p>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>

