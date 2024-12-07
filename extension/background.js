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

const API_URL = 'https://panel-production-5838.up.railway.app';
const auth = new ExtensionAuth(API_URL);

// Initialize authentication when extension loads
chrome.runtime.onInstalled.addListener(async () => {
    await auth.init();
});

// Listen for API key updates
chrome.runtime.onMessage.addListener(async (message, sender, sendResponse) => {
    if (message.type === 'SET_API_KEY') {
        const success = await auth.setApiKey(message.apiKey);
        sendResponse({ success });
        return true; // Keep the message channel open for async response
    }
    
    if (message.type === 'CHECK_AUTH') {
        const isAuthenticated = auth.isAuthenticated();
        const user = auth.getUser();
        sendResponse({ isAuthenticated, user });
        return true;
    }
    
    if (message.type === 'LOGOUT') {
        await auth.logout();
        sendResponse({ success: true });
        return true;
    }
});

// Function to make authenticated requests
async function makeAuthenticatedRequest(url, options = {}) {
    if (!auth.isAuthenticated()) {
        throw new Error('Not authenticated');
    }
    
    // Add API key to request headers
    const headers = {
        ...options.headers,
        'X-API-Key': auth.apiKey
    };
    
    const response = await fetch(url, {
        ...options,
        headers
    });
    
    // If we get a 401, try to re-authenticate
    if (response.status === 401) {
        const isValid = await auth.validateApiKey(auth.apiKey);
        if (!isValid) {
            // If validation fails, clear auth and throw error
            await auth.logout();
            throw new Error('Authentication failed');
        }
        
        // Retry the request with the re-validated API key
        return fetch(url, {
            ...options,
            headers: {
                ...options.headers,
                'X-API-Key': auth.apiKey
            }
        });
    }
    
    return response;
}

// Example of how to use the authenticated request
async function fetchUserData() {
    try {
        const response = await makeAuthenticatedRequest(`${API_URL}/api/user-data.php`);
        if (!response.ok) {
            throw new Error('Failed to fetch user data');
        }
        return await response.json();
    } catch (error) {
        console.error('Error fetching user data:', error);
        throw error;
    }
}
