// Main JS for ImageProof

function toggleNav() {
  const nav = document.querySelector('.nav-links');
  if (nav) {
    nav.classList.toggle('open');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const navToggle = document.getElementById('nav-toggle');
  if (navToggle) {
    navToggle.addEventListener('click', toggleNav);
  }
});