// Simple progress bar test - copy and paste this into browser console
console.log('🧪 Testing progress bar manually...');

// Test 1: Check if Alpine.js is loaded
if (window.Alpine) {
    console.log('✅ Alpine.js is loaded');
} else {
    console.log('❌ Alpine.js is NOT loaded');
}

// Test 2: Find progress bar element
const progressBar = document.querySelector('.progress-container');
if (progressBar) {
    console.log('✅ Progress bar element found');
    progressBar.style.display = 'block';
    progressBar.style.background = 'yellow';
    progressBar.style.border = '3px solid red';
    console.log('🎯 Progress bar should now be visible with yellow background');
} else {
    console.log('❌ Progress bar element not found');
}

// Test 3: Check for Alpine.js data
const quoteWizard = document.querySelector('[x-data*="quoteWizard"]');
if (quoteWizard) {
    console.log('✅ Quote wizard element found');
} else {
    console.log('❌ Quote wizard element not found');
}

// Test 4: Force show progress bar if Alpine is working
if (window.Alpine && quoteWizard) {
    console.log('🚀 Attempting to force show progress bar...');
    // This should trigger the progress bar if Alpine is working
    window.Alpine.store('data', { showProgress: true });
}

console.log('🔍 Check above for test results');