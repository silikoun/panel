document.addEventListener('DOMContentLoaded', async function() {
  const tokenInput = document.getElementById('apiToken');
  const saveButton = document.getElementById('saveToken');
  const statusDiv = document.getElementById('status');

  // Check current authentication status
  chrome.runtime.sendMessage({ type: 'CHECK_AUTH' }, function(response) {
    if (response.isAuthenticated) {
      showAuthenticatedState(response.user);
    } else {
      showUnauthenticatedState();
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

  function showAuthenticatedState(user) {
    const container = document.querySelector('.container');
    container.innerHTML = `
      <h2>WooCommerce Scraper</h2>
      <div class="user-info">
        <p>Logged in as: ${user.email}</p>
      </div>
      <button id="logoutButton">Logout</button>
      <div id="status" class="status"></div>
    `;

    // Add logout handler
    document.getElementById('logoutButton').addEventListener('click', handleLogout);
  }

  function showUnauthenticatedState() {
    const container = document.querySelector('.container');
    container.innerHTML = `
      <h2>WooCommerce Scraper</h2>
      <div class="input-group">
        <label for="apiToken">API Key</label>
        <input type="text" id="apiToken" placeholder="Paste your API key here">
      </div>
      <button id="saveToken">Save API Key</button>
      <div id="status" class="status"></div>
    `;

    // Re-add save handler
    document.getElementById('saveToken').addEventListener('click', handleSaveToken);
  }

  async function handleLogout() {
    chrome.runtime.sendMessage({ type: 'LOGOUT' }, function(response) {
      if (response.success) {
        showUnauthenticatedState();
        showStatus('Logged out successfully');
      }
    });
  }

  async function handleSaveToken() {
    const token = document.getElementById('apiToken').value.trim();
    
    if (!token) {
      showStatus('Please enter an API key', true);
      return;
    }

    // Try to set the API key
    chrome.runtime.sendMessage({ 
      type: 'SET_API_KEY',
      apiKey: token 
    }, function(response) {
      if (response.success) {
        // Check auth status again to update UI
        chrome.runtime.sendMessage({ type: 'CHECK_AUTH' }, function(response) {
          if (response.isAuthenticated) {
            showAuthenticatedState(response.user);
            showStatus('API key saved successfully!');
          }
        });
      } else {
        showStatus('Invalid API key', true);
      }
    });
  }

  // Add initial event listener for save button
  if (saveButton) {
    saveButton.addEventListener('click', handleSaveToken);
  }
});
