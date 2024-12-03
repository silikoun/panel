// Listen for token updates
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === 'TOKEN_UPDATED') {
    // Store token for future requests
    chrome.storage.sync.set({ apiToken: message.token });
  }
});

// Function to get stored token
async function getStoredToken() {
  return new Promise((resolve) => {
    chrome.storage.sync.get(['apiToken'], function(result) {
      resolve(result.apiToken || null);
    });
  });
}

// Example of how to use the token in requests
async function makeAuthenticatedRequest(url, options = {}) {
  const token = await getStoredToken();
  if (!token) {
    throw new Error('No API token found');
  }

  // Add token to request headers
  const headers = {
    ...options.headers,
    'Authorization': `Bearer ${token}`
  };

  return fetch(url, {
    ...options,
    headers
  });
}
