document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('out').addEventListener('click', function() {
        if (confirm('Вы уверены, что хотите выйти?')) {
            window.location.href = 'logout.php';
        }
    });
    
    const ideaBtn = document.getElementById('idea');
    if (ideaBtn) {
        ideaBtn.addEventListener('click', function() {
            window.location.href = 'idea.html';
        });
    }
});