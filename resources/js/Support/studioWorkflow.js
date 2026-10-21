export const managerQueues = [
    { id: 'review', label: 'Payment problems', tone: 'stage-error', action: 'Review payment', why: 'Failed or mismatched payment; verify the receipt before asking for another payment.' },
    { id: 'quotes', label: 'Needs a quote', tone: 'stage-waiting', action: 'Review brief', why: 'New custom briefs without a price; confirm requirements, quote and timing with the customer.' },
    { id: 'payment', label: 'Awaiting payment', tone: 'stage-waiting', action: 'Follow up', why: 'Pending checkout payments or priced custom work; check the payment evidence before confirming receipt.' },
    { id: 'fulfilment', label: 'Paid · start work', tone: 'stage-working', action: 'Plan fulfilment', why: 'Payment confirmed; review sizes, items and delivery details before beginning fulfilment.' },
    { id: 'production', label: 'In production', tone: 'stage-working', action: 'Review progress', why: 'Orders in design or sewing; check progress and update the customer when work advances.' },
    { id: 'pickup', label: 'Waiting for pickup', tone: 'stage-ready', action: 'Review pickups', why: 'Ready at the shop. The customer collects at the till with the reference and the phone they ordered with.' },
    { id: 'dispatch', label: 'Ready to dispatch', tone: 'stage-ready', action: 'Review dispatch', why: 'Paid orders marked complete; verify the parcel and delivery details before marking dispatched.' },
];
