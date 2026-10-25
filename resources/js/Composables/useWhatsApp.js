/**
 * Builds pre-filled WhatsApp click-to-chat links for product enquiries.
 *
 * Voice: the customer writing to the store — courteous, plain language,
 * only lines that carry real information. WhatsApp *bold* is used for the
 * few things the studio scans for (item name, price).
 */
import { useCurrency } from '@/Composables/useCurrency';

export function useWhatsApp() {
    const { formatAmount } = useCurrency();

    /**
     * Helper to encode URL characters correctly for WhatsApp
     */
    const rawurlencode = (str) => {
        return encodeURIComponent(str).replace(/[!'()*]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase());
    };

    /**
     * Generates a clear, professional enquiry message for a product.
     */
    const generateProductMessage = (product, options = {}, pageProps = {}) => {
        const { topSize, bottomSize, labels = ['Top size', 'Bottom size'] } = options;

        // Only mention sizes the customer actually chose, named as on the product page.
        const sizeLines = [];
        if (product.garment_type === 'set') {
            if (topSize) sizeLines.push(`${labels[0]}: ${topSize}`);
            if (bottomSize) sizeLines.push(`${labels[1]}: ${bottomSize}`);
        } else if (topSize || bottomSize) {
            sizeLines.push(`Size: ${topSize || bottomSize}`);
        }

        const formattedPrice = formatAmount(product.price, pageProps);

        const lines = [
            `Hello ${pageProps.store?.name || 'Serana'},`,
            '',
            "I'd like to enquire about this piece:",
            '',
            `*${product.name}*`,
            ...sizeLines,
            `Price: ${formattedPrice}`,
            '',
            window.location.href,
            '',
            'Is it available? Thank you.',
        ];

        return lines.join('\n');
    };

    /**
     * Returns the full WhatsApp URL
     */
    const getWhatsAppUrl = (product, options = {}, pageProps = {}) => {
        const message = generateProductMessage(product, options, pageProps);
        // Single managed source: the normalized store number shared from the server.
        const whatsappNumber = pageProps.store?.whatsapp || pageProps.whatsapp_number || '';
        return `https://wa.me/${whatsappNumber}?text=${rawurlencode(message)}`;
    };

    return {
        getWhatsAppUrl,
        generateProductMessage
    };
}
