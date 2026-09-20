<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Your order {{ $order->order_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f8;font-family:Arial,Helvetica,sans-serif;color:#1f2540;">
  {{-- preheader: the line shown next to the subject in the inbox --}}
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    Order {{ $order->order_number }} · total ₦{{ number_format((float) $order->total, 2) }}
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f8;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">

          <tr>
            <td align="center" style="background:#ffffff;padding:20px 24px;border-bottom:4px solid #1a237e;">
              <img src="{{ $baseUrl }}/images/logo2.png" alt="{{ $store }}" height="46" style="display:block;border:0;height:46px;">
            </td>
          </tr>

          <tr>
            <td style="padding:28px 28px 8px;">
              <p style="margin:0 0 6px;font-size:16px;">Hello {{ $user->name }},</p>
              <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;">
                {{ $paid ? 'Thank you — your payment was received' : 'Thank you — we have received your order' }}
              </h1>

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0fb;border-radius:10px;">
                <tr>
                  <td style="padding:14px 18px;">
                    <div style="font-size:12px;color:#5b6180;">Your order number</div>
                    <div style="font-size:24px;font-weight:bold;letter-spacing:.5px;">{{ $order->order_number }}</div>
                    <div style="font-size:12px;color:#5b6180;margin-top:4px;">Placed {{ $order->created_at->format('j M Y, g:i a') }}</div>
                  </td>
                </tr>
              </table>

              @if ($paid)
                <p style="margin:16px 0 0;padding:12px 14px;background:#e8f7ee;border-radius:8px;font-size:14px;line-height:1.5;color:#146c3a;">
                  <strong>Payment confirmed.</strong> We are now getting your order ready.
                </p>
              @else
                <p style="margin:16px 0 0;padding:12px 14px;background:#fff4e0;border-radius:8px;font-size:14px;line-height:1.5;color:#8a5a00;">
                  <strong>Awaiting payment confirmation.</strong> We have your bank transfer details and will start processing
                  your order as soon as the payment is confirmed. Orders that stay unpaid may be cancelled.
                </p>
              @endif
            </td>
          </tr>

          <tr>
            <td style="padding:18px 28px 0;">
              <h2 style="margin:0 0 8px;font-size:16px;">What you ordered</h2>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                <tr style="color:#5b6180;font-size:12px;text-transform:uppercase;letter-spacing:.4px;">
                  <td style="padding:6px 0;border-bottom:1px solid #e3e5f0;">Item</td>
                  <td align="center" style="padding:6px 8px;border-bottom:1px solid #e3e5f0;">Qty</td>
                  <td align="right" style="padding:6px 0;border-bottom:1px solid #e3e5f0;">Price</td>
                  <td align="right" style="padding:6px 0 6px 10px;border-bottom:1px solid #e3e5f0;">Total</td>
                </tr>
                @foreach ($order_items as $line)
                  <tr>
                    {{-- the stored name already reads "Product - colour - size" --}}
                    <td style="padding:10px 0;border-bottom:1px solid #eef0f6;">{{ $line->product_name }}</td>
                    <td align="center" style="padding:10px 8px;border-bottom:1px solid #eef0f6;">{{ $line->quantity }}</td>
                    <td align="right" style="padding:10px 0;border-bottom:1px solid #eef0f6;">₦{{ number_format((float) $line->price, 2) }}</td>
                    <td align="right" style="padding:10px 0 10px 10px;border-bottom:1px solid #eef0f6;">₦{{ number_format((float) $line->total, 2) }}</td>
                  </tr>
                @endforeach
              </table>

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin-top:10px;">
                @php
                  // shown only when delivery is really part of what is charged (total above the items), so the lines always add up
                  $delivery = round((float) $order->total - (float) $order->amount, 2);
                @endphp
                @if ($delivery > 0)
                  <tr>
                    <td style="padding:4px 0;color:#5b6180;">Items</td>
                    <td align="right" style="padding:4px 0;">₦{{ number_format((float) $order->amount, 2) }}</td>
                  </tr>
                  <tr>
                    <td style="padding:4px 0;color:#5b6180;">Delivery</td>
                    <td align="right" style="padding:4px 0;">₦{{ number_format($delivery, 2) }}</td>
                  </tr>
                @endif
                <tr>
                  <td style="padding:8px 0 0;border-top:2px solid #1a237e;font-size:17px;font-weight:bold;">Total</td>
                  <td align="right" style="padding:8px 0 0;border-top:2px solid #1a237e;font-size:17px;font-weight:bold;">₦{{ number_format((float) $order->total, 2) }}</td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:22px 28px 0;">
              <h2 style="margin:0 0 8px;font-size:16px;">Delivery details</h2>
              <p style="margin:0;font-size:14px;line-height:1.6;">
                {{ $user->name }}@if ($user->phone) · {{ $user->phone }}@endif<br>
                {{ $order->address }}<br>
                @if ($order->nearest_bustop) Nearest bus stop: {{ $order->nearest_bustop }}<br>@endif
                {{ str_replace('/', ', ', trim((string) $order->location, '/')) }}
              </p>
              @if ($order->notes)
                <p style="margin:10px 0 0;font-size:13px;color:#5b6180;">Your note: {{ $order->notes }}</p>
              @endif
              <p style="margin:10px 0 0;font-size:13px;color:#5b6180;">Payment method: {{ $order->payment_method }}</p>
            </td>
          </tr>

          <tr>
            <td align="center" style="padding:26px 28px 8px;">
              <a href="{{ $trackUrl }}" style="display:inline-block;background:#1a237e;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:13px 26px;border-radius:8px;">Track your order</a>
              <p style="margin:12px 0 0;font-size:12px;color:#5b6180;">
                Or open {{ $baseUrl }}/track/order and enter your order number and email.
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:22px 28px 26px;">
              <p style="margin:0;font-size:12px;line-height:1.6;color:#8a90ad;border-top:1px solid #e3e5f0;padding-top:14px;">
                You are receiving this because an order was placed on {{ $store }} with this email address.
                If that was not you, please reply to this email and let us know.
              </p>
              <p style="margin:8px 0 0;font-size:12px;color:#8a90ad;">© {{ date('Y') }} {{ $store }}</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
