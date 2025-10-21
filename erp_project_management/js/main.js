// JOSHUA
// Fetch and display resources
function loadResources() {
    fetch('/api/resource.php')
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok');
            }
            return res.json();
        })
        .then(data => {
            if (data.status === 'success') {
                const resources = data.data;
                // Populate resource dropdown or table
                console.log('Resources loaded:', resources);
                // Example: show name, type, availability
            } else {
                console.error('Error loading resources:', data.message);
                alert('Error loading resources: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load resources. Please check console for details.');
        });
}

// Assign resource to task
function assignResource(taskId, resourceId, percent) {
    fetch('/api/allocate_resource.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            task_id: taskId,
            resource_id: resourceId,
            allocation_percent: percent
        })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status === 'success') {
            alert('Resource assigned!');
            loadResources(); // Refresh availability
        } else {
            alert(response.message);
        }
    });
}
// JOSHUA







// General scripts for UI interaction
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            alert.style.opacity = '0';
        });
    }, 3000);
});