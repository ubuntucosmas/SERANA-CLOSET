export const managerQueues = [
    { id: 'attention', label: 'Needs attention', tone: 'stage-error', action: 'Review exceptions', why: 'Review refunds, payment evidence and stock decisions before progressing these orders.' },
    { id: 'review', label: 'Payment needs review', tone: 'stage-error', action: 'Review payment', why: 'Failed or mismatched payment; verify the receipt before asking for another payment.' },
    { id: 'quotes', label: 'Needs a quote', tone: 'stage-waiting', action: 'Review brief', why: 'New custom briefs without a price; confirm requirements, quote and timing with the customer.' },
    { id: 'payment', label: 'Awaiting payment', tone: 'stage-waiting', action: 'Follow up', why: 'Pending checkout payments or priced custom work; check the payment evidence before confirming receipt.' },
    { id: 'fulfilment', label: 'New / to start', tone: 'stage-working', action: 'Plan fulfilment', why: 'Payment confirmed; review sizes, items and delivery details before beginning fulfilment.' },
    { id: 'production', label: 'In production', tone: 'stage-working', action: 'Review progress', why: 'Orders in design or sewing; check progress and update the customer when work advances.' },
    { id: 'complete', label: 'Completed', tone: 'stage-complete', action: 'Review history', why: 'Orders already dispatched or collected. Review their saved history.' },
    { id: 'pickup', label: 'Ready for pickup', tone: 'stage-ready', action: 'Review pickups', why: 'Ready at the shop. The customer collects at Sales Desk with the reference and the phone they ordered with.' },
    { id: 'dispatch', label: 'Ready to ship', tone: 'stage-ready', action: 'Review dispatch', why: 'Paid orders marked complete; verify the parcel and delivery details before marking dispatched.' },
];
