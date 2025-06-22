document.querySelector('form').addEventListener('submit', async function(e) {
      e.preventDefault();
      const form = e.target;
      const formData = new FormData(form);
      const response = await fetch('signup.php', {
          method: 'POST',
          body: formData
      });
      const result = await response.json();
      let errorDiv = document.getElementById('signup-error-message');
      if (result.success) {
          errorDiv.style.color = 'green';
          errorDiv.textContent = result.message;
          setTimeout(() => { window.location.href = 'login.php'; }, 1500);
      } else {
          errorDiv.style.color = 'red';
          errorDiv.textContent = result.message;
      }
  });