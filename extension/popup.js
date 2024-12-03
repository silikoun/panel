document.addEventListener('DOMContentLoaded', function() {
  const tokenInput = document.getElementById('apiToken');
  const saveButton = document.getElementById('saveToken');
  const statusDiv = document.getElementById('status');

  // Load existing token if any
  chrome.storage.sync.get(['apiToken'], function(result) {
    if (result.apiToken) {
      tokenInput.value = result.apiToken;
    }
  });

  function showStatus(message, isError = false) {
    statusDiv.textContent = message;
    statusDiv.style.display = 'block';
    statusDiv.className = 'status ' + (isError ? 'error' : 'success');
    setTimeout(() => {
      statusDiv.style.display = 'none';
    }, 3000);
  }

  saveButton.addEventListener('click', function() {
    const token = tokenInput.value.trim();
    
    if (!token) {
      showStatus('Please enter an API token', true);
      return;
    }

    // Save token to Chrome storage
    chrome.storage.sync.set({ apiToken: token }, function() {
      showStatus('Token saved successfully!');
      
      // Notify background script
      chrome.runtime.sendMessage({ 
        type: 'TOKEN_UPDATED',
        token: token 
      });
    });
  });
});
