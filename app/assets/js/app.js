document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    
    if (!loginForm) {
        console.error('Login form not found');
        return;
    }
    
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const errorDiv = document.getElementById('errorMessage');
        const submitBtn = this.querySelector('button[type="submit"]');
        
        // Hide previous errors
        if (errorDiv) {
            errorDiv.style.display = 'none';
        }
        
        // Disable button
        submitBtn.disabled = true;
        submitBtn.textContent = 'AUTHENTICATING...';
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('../auth/login.php', {
                method: 'POST',
                body: formData
            });
            
            // Check if response is OK
            if (!response.ok) {
                throw new Error('Server error: ' + response.status);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                throw new Error('Server returned invalid response');
            }
            
            const data = await response.json();
            
            console.log('Login response:', data);
            
            if (data.success) {
                console.log('Login successful, redirecting to:', data.redirect);
                // Use window.location.href for redirect
                window.location.href = data.redirect;
            } else {
                if (errorDiv) {
                    errorDiv.textContent = data.message || 'Login failed. Please try again.';
                    errorDiv.style.display = 'block';
                }
                submitBtn.disabled = false;
                submitBtn.textContent = 'SECURE ACCESS →';
            }
        } catch (error) {
            console.error('Login error:', error);
            if (errorDiv) {
                errorDiv.textContent = 'Connection error: ' + error.message;
                errorDiv.style.display = 'block';
            }
            submitBtn.disabled = false;
            submitBtn.textContent = 'SECURE ACCESS →';
        }
    });
});