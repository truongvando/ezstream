<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Test CSRF</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .form-group { margin: 10px 0; }
        input, button { padding: 10px; margin: 5px; }
        .result { margin: 20px 0; padding: 10px; background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Test CSRF Token</h1>
    
    <div class="result">
        <h3>Current Session Info:</h3>
        <p><strong>CSRF Token:</strong> <?php echo e(csrf_token()); ?></p>
        <p><strong>Session ID:</strong> <?php echo e(session()->getId()); ?></p>
        <p><strong>Meta CSRF:</strong> <span id="meta-csrf"></span></p>
    </div>

    <form id="test-form" action="/test-csrf" method="POST">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label>Test Input:</label>
            <input type="text" name="test" value="Hello CSRF" />
        </div>
        <button type="submit">Test CSRF POST</button>
    </form>

    <div id="result" class="result" style="display: none;">
        <h3>Result:</h3>
        <pre id="result-content"></pre>
    </div>

    <script>
        // Show meta CSRF token
        const metaCsrf = document.querySelector('meta[name="csrf-token"]');
        document.getElementById('meta-csrf').textContent = metaCsrf ? metaCsrf.content : 'NOT FOUND';

        // Handle form submission
        document.getElementById('test-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('/test-csrf', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const result = await response.json();
                document.getElementById('result-content').textContent = JSON.stringify(result, null, 2);
                document.getElementById('result').style.display = 'block';
                
            } catch (error) {
                document.getElementById('result-content').textContent = 'Error: ' + error.message;
                document.getElementById('result').style.display = 'block';
            }
        });
    </script>
</body>
</html>
<?php /**PATH D:\laragon\www\ezstream\resources\views/test-csrf.blade.php ENDPATH**/ ?>