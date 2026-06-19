/**
 * Builds pre-filled WhatsApp click-to-chat links for product enquiries.
 */
export function useWhatsApp() {
    
    /**
     * Helper to encode URL characters correctly for WhatsApp
     */
    const rawurlencode = (str) => {
        return encodeURIComponent(str).replace(/[!'()*]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase());
    };

    /**
     * Generates a structured message for product enquiries
     */
    const generateProductMessage = (product, options = {}, pageProps = {}) => {
        const baseUrl = window.location.origin;
        const imageUrl = product.image_url?.startsWith('http') 
            ? product.image_url 
            : `${baseUrl}/${product.image_url?.replace(/^\//, '')}`;
        
        let variantMetadata = '';
        const { topSize, bottomSize } = options;

        if (product.garment_type === 'set') {
            variantMetadata = `• Configuration: FULL SET\n• Hoodie Size: ${topSize || 'Unselected'}\n• Jogger Size: ${bottomSize || 'Unselected'}`;
        } else if (product.garment_type === 'hoodie') {
            variantMetadata = `• Configuration: SINGLE HOODIE\n• Size: ${topSize || 'Unselected'}`;
        } else if (product.garment_type === 'jogger') {
            variantMetadata = `• Configuration: SINGLE JOGGER\n• Size: ${bottomSize || 'Unselected'}`;
        } else {
            variantMetadata = `• Configuration: STANDARD\n• Size: ${topSize || bottomSize || 'Unselected'}`;
        }

        const currencySymbol = pageProps.currency_symbol || 'Ksh';
        const formattedPrice = `${currencySymbol} ${Number(product.price).toLocaleString()}`;

        const message = `🏁 *NEW ORDER ENQUIRY* 🏁\n\n` +
                        `*[ 01: ITEM ]*\n` +
                        `• Name: ${product.name}\n` +
                        `${variantMetadata}\n` +
                        `• Price: ${formattedPrice}\n\n` +
                        `*[ 02: REFERENCE ]*\n` +
                        `• URL: ${window.location.href}\n` +
                        `• Image: ${imageUrl}\n\n` +
                        `--------------------------\n` +
                        `I'm interested in this piece. Can you help me with the purchase?\n` +
                        `Sent via Serana Studio.`;

        return message;
    };

    /**
     * Returns the full WhatsApp URL
     */
    const getWhatsAppUrl = (product, options = {}, pageProps = {}) => {
        const message = generateProductMessage(product, options, pageProps);
        // Single managed source: the normalized store number shared from the server.
        const whatsappNumber = pageProps.whatsapp_number || '';
        return `https://wa.me/${whatsappNumber}?text=${rawurlencode(message)}`;
    };

    return {
        getWhatsAppUrl,
        generateProductMessage
    };
}
