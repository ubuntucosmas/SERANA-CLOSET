// Display existing register keys without changing session identifiers or API payloads.
export const registerLabel = value => String(value || '').replace(/^Till(?=\s|$)/i, 'Register');
