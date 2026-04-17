export function useCurrency() {
    /**
     * Formats a given numeric amount into a localized currency string.
     * The currency symbol is configurable by the admin panel via Inertia's globally shared properties,
     * allowing for seamless internationalization and scalability.
     * 
     * @param {Number|String} amount - The numerical value to format.
     * @param {Object} pageProps - The global $page.props object from Inertia.
     * @returns {String} The formatted currency string.
     */
    const formatAmount = (amount, pageProps) => {
        // Parse the amount to ensure it is numeric, defaulting to 0 if invalid
        const numericAmount = Number(amount) || 0;

        // Dynamically fetch the currency symbol from theme settings, fallback to 'KSh'
        const currencySymbol = pageProps?.theme_settings?.currency || 'KSh';

        return `${currencySymbol} ${numericAmount.toLocaleString()}`;
    };

    return {
        formatAmount
    };
}
