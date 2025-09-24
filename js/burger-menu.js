function toggleMobileMenu() {
    const mobileMenu = document.getElementById('mobileMenu');
    const burgerBtn = document.querySelector('.burger-btn');
    
    if (mobileMenu && burgerBtn) {
        mobileMenu.classList.toggle('active');
        burgerBtn.classList.toggle('active');
    }
}

function closeMobileMenu() {
    const mobileMenu = document.getElementById('mobileMenu');
    const burgerBtn = document.querySelector('.burger-btn');
    
    if (mobileMenu && burgerBtn) {
        mobileMenu.classList.remove('active');
        burgerBtn.classList.remove('active');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(event) {
        const mobileMenu = document.getElementById('mobileMenu');
        const burgerBtn = document.querySelector('.burger-btn');
        
        if (mobileMenu && burgerBtn) {
            if (!burgerBtn.contains(event.target) && !mobileMenu.contains(event.target)) {
                closeMobileMenu();
            }
        }
    });
    
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeMobileMenu();
        }
    });
});