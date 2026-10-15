// برای تعاملات بعدی (مانند بارگذاری داینامیک، تاگل موبایل و ...)
console.log('FragCity ready | RTL Modern Design');

// ********** همبرگری سایدبار **********
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarNav = document.getElementById('sidebarNav');

if (sidebarToggle && sidebarNav) {
    sidebarToggle.addEventListener('click', function() {
        sidebarNav.classList.toggle('show');
    });
}

// ********** همبرگری تب‌ها **********
const tabHamburger = document.getElementById('tabHamburger');
const tabsWrapper = document.getElementById('tabsWrapper');

if (tabHamburger && tabsWrapper) {
    tabHamburger.addEventListener('click', function() {
        tabsWrapper.classList.toggle('show');
    });
}

// بستن تب‌ها هنگام کلیک روی یک تب
document.querySelectorAll('#mainTabs .nav-link').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 800 && tabsWrapper) {
            tabsWrapper.classList.remove('show');
        }
    });
});