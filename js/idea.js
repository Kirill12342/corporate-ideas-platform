const btn = document.getElementById('dropdown-btn');
const list = document.getElementById('dropdown-list');

  btn.addEventListener('click', () => {
    list.style.display = list.style.display === 'block' ? 'none' : 'block';
});

  list.querySelectorAll('.dropdown-item').forEach(item => {
    item.addEventListener('click', () => {
      btn.textContent = item.textContent;  
      list.style.display = 'none';         
    });
});

  window.addEventListener('click', e => {
    if (e.target !== btn && !list.contains(e.target)) {
      list.style.display = 'none';
    }
});