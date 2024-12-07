class ExtensionAuth {
    constructor(apiUrl) {
        this.apiUrl = apiUrl;
        this.apiKey = null;
        this.user = null;
    }

    /**
     * Initialize authentication
     * @returns {Promise<boolean>} True if authentication successful
     */
    async init() {
        try {
            // Try to get API key from storage
            const stored = await chrome.storage.local.get(['apiKey']);
            if (stored.apiKey) {
                // Validate the stored API key
                const isValid = await this.validateApiKey(stored.apiKey);
                if (isValid) {
                    this.apiKey = stored.apiKey;
                    return true;
                }
            }
            return false;
        } catch (error) {
            console.error('Auth initialization failed:', error);
            return false;
        }
    }

    /**
     * Validate an API key with the server
     * @param {string} apiKey - The API key to validate
     * @returns {Promise<boolean>} True if API key is valid
     */
    async validateApiKey(apiKey) {
        try {
            const response = await fetch(`${this.apiUrl}/api/extension_auth.php`, {
                method: 'POST',
                headers: {
                    'X-API-Key': apiKey
                }
            });

            const data = await response.json();
            if (data.success) {
                this.user = data.user;
                return true;
            }
            return false;
        } catch (error) {
            console.error('API key validation failed:', error);
            return false;
        }
    }

    /**
     * Set a new API key
     * @param {string} apiKey - The new API key to set
     * @returns {Promise<boolean>} True if API key was set successfully
     */
    async setApiKey(apiKey) {
        try {
            // Validate the API key first
            const isValid = await this.validateApiKey(apiKey);
            if (isValid) {
                // Store the API key
                await chrome.storage.local.set({ apiKey });
                this.apiKey = apiKey;
                return true;
            }
            return false;
        } catch (error) {
            console.error('Failed to set API key:', error);
            return false;
        }
    }

    /**
     * Get the current user
     * @returns {Object|null} The current user or null if not authenticated
     */
    getUser() {
        return this.user;
    }

    /**
     * Check if user is authenticated
     * @returns {boolean} True if user is authenticated
     */
    isAuthenticated() {
        return this.apiKey !== null && this.user !== null;
    }

    /**
     * Clear authentication
     * @returns {Promise<void>}
     */
    async logout() {
        try {
            await chrome.storage.local.remove(['apiKey']);
            this.apiKey = null;
            this.user = null;
        } catch (error) {
            console.error('Logout failed:', error);
        }
    }
}
