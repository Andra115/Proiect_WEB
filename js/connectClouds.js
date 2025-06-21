
  function connectCloudService(provider) {
    fetch(`/../php/cloud/${provider}.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.auth_url) {
            window.location.href = data.auth_url;
        } else {
            alert('Error: Authentication URL not provided');
        }
    })
    .catch(error => {
        alert('Error connecting to cloud service: ' + error);
    });
}


