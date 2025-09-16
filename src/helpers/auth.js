/**
 * SureFeedback Authentication Helpers
 */

export const authenticateRedirect = () => {
  const { sureFeedbackAdmin } = window;
  
  if (!sureFeedbackAdmin || !sureFeedbackAdmin.connection) {
    console.error('SureFeedback admin data or connection info not available');
    return;
  }

  const { connection } = sureFeedbackAdmin;
  
  // Generate a state token for security
  const state = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
  
  // Build connection URL parameters following the PHP pattern
  const params = new URLSearchParams({
    source: 'wordpress',
    action: 'connect_site',
    callback_url: connection.callback_url,
    state: state,
    site_data: btoa(JSON.stringify(connection.site_data)), // base64 encode like PHP version
    // plugin_version: connection.site_data.plugin_version
  });

  // Construct the connection URL using localized app URL
  const connectUrl = `${connection.app_url}/connect?${params.toString()}`;
  
  console.log('Redirecting to:', connectUrl);
  
  // Redirect to parent site for authentication
  window.open(connectUrl, '_self');
};

export const getUrlParam = (param) => {
  return new URL(window.location.href).searchParams.get(param);
};