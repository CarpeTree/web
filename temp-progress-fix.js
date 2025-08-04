// Temporary progress bar fix - paste in console
console.log('🔧 Adding temporary progress bar...');

// Create a simple progress overlay
const progressOverlay = document.createElement('div');
progressOverlay.innerHTML = `
<div style="
    position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
    background: white; padding: 30px; border: 3px solid #2c5f2d;
    border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    z-index: 10000; text-align: center; font-family: Arial;
    display: none;" id="tempProgress">
    <h3 style="color: #2c5f2d; margin: 0 0 15px 0;">🌲 Processing Your Quote</h3>
    <div style="width: 300px; height: 20px; background: #e0e0e0; border-radius: 10px; overflow: hidden;">
        <div id="tempProgressBar" style="height: 100%; background: linear-gradient(90deg, #2c5f2d, #4a8f4d); width: 0%; transition: width 0.5s;"></div>
    </div>
    <p id="tempProgressText" style="margin: 15px 0 0 0; color: #666;">Starting submission...</p>
</div>`;
document.body.appendChild(progressOverlay);

// Override form submission to show progress
const form = document.querySelector('form');
if (form) {
    const originalSubmit = form.onsubmit;
    form.onsubmit = function(e) {
        e.preventDefault();
        
        // Show progress overlay
        document.getElementById('tempProgress').style.display = 'block';
        const bar = document.getElementById('tempProgressBar');
        const text = document.getElementById('tempProgressText');
        
        // Animate progress
        setTimeout(() => { bar.style.width = '25%'; text.textContent = 'Uploading files...'; }, 500);
        setTimeout(() => { bar.style.width = '50%'; text.textContent = 'Processing media...'; }, 2000);
        setTimeout(() => { bar.style.width = '75%'; text.textContent = 'AI analysis starting...'; }, 4000);
        setTimeout(() => { bar.style.width = '100%'; text.textContent = 'Complete! Redirecting...'; }, 6000);
        
        // Submit the actual form
        setTimeout(() => {
            if (originalSubmit) originalSubmit.call(form, e);
            else form.submit();
        }, 1000);
    };
    
    console.log('✅ Temporary progress bar installed!');
} else {
    console.log('❌ Form not found');
}