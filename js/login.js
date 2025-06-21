document.querySelector('form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);

        const response = await fetch('login.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        let errorDiv = document.getElementById('login-error-message');
        if (result.success) {
            const remember = form.querySelector('input[name="remember"]').checked;
            if (remember) {
                localStorage.setItem('jwt', result.jwt);
                sessionStorage.setItem('jwt', result.jwt);
            } else {
                sessionStorage.setItem('jwt', result.jwt);
            }
            window.location.href = 'welcome.php';
        } else {
            errorDiv.style.color = 'red';
            errorDiv.textContent = result.message;
        }
    });