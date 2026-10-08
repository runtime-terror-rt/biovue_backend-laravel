@extends('emails.layouts.master')

@section('title', 'Weekly Business Summary - BioVue')

@section('content')
    <div style="text-align: center; margin-bottom: 25px;">
        <span style="background-color: #ede9fe; color: #6d28d9; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
            Weekly Business Briefing
        </span>
        <h2 style="font-size: 24px; color: #1e1b4b; font-weight: 700; margin: 12px 0 6px 0;">
            Weekly Performance Summary
        </h2>
        <p style="color: #64748b; font-size: 14px; margin: 0;">
            Report Period: <strong>{{ $reportPeriod }}</strong>
        </p>
    </div>

    <p style="font-size: 15px; color: #334155;">Hello <strong>{{ $supplierName }}</strong>,</p>
    <p style="font-size: 14px; color: #475569; margin-top: -5px;">
        Here is your weekly recap of catalog visibility, AI match recommendations, and client interactions across the BioVue wellness platform.
    </p>

    <!-- Key Metrics Grid -->
    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: separate; border-spacing: 10px 0;">
        <tr>
            <td width="33%" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; text-align: center;">
                <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Product Clicks</div>
                <div style="font-size: 22px; font-weight: 800; color: #0284c7; margin-top: 6px;">{{ $stats['product_clicks'] ?? 142 }}</div>
                <div style="font-size: 11px; color: #16a34a; margin-top: 4px;">+18% vs last wk</div>
            </td>
            <td width="33%" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; text-align: center;">
                <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">AI Match Suggestions</div>
                <div style="font-size: 22px; font-weight: 800; color: #7c3aed; margin-top: 6px;">{{ $stats['ai_matches'] ?? 89 }}</div>
                <div style="font-size: 11px; color: #16a34a; margin-top: 4px;">High affinity</div>
            </td>
            <td width="33%" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; text-align: center;">
                <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Inquiries / Messages</div>
                <div style="font-size: 22px; font-weight: 800; color: #059669; margin-top: 6px;">{{ $stats['new_messages'] ?? 14 }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Active clients</div>
            </td>
        </tr>
    </table>

    <!-- Active Catalog Summary Box -->
    <div style="background-color: #faf5ff; border: 1px solid #e9d5ff; border-radius: 10px; padding: 16px 20px; margin: 20px 0;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="vertical-align: middle;">
                    <div style="font-weight: 700; color: #581c87; font-size: 14px;">Active Catalog Status</div>
                    <div style="color: #6b21a8; font-size: 13px; margin-top: 3px;">
                        <strong>{{ $stats['published_products'] ?? 12 }}</strong> published products live in BioVue directory
                    </div>
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    <span style="background: #22c55e; color: #ffffff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                        Active & Listed
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Top Performing Products -->
    @if(!empty($topProducts))
        <div style="margin: 25px 0;">
            <h3 style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                Top Recommended Products This Week
            </h3>
            <table width="100%" cellpadding="10" cellspacing="0" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; font-size: 13px;">
                <tr style="background-color: #f1f5f9; color: #475569; font-weight: 600;">
                    <th align="left" style="border-bottom: 1px solid #e2e8f0;">Product Name</th>
                    <th align="center" style="border-bottom: 1px solid #e2e8f0;">Category</th>
                    <th align="center" style="border-bottom: 1px solid #e2e8f0;">Client Matches</th>
                    <th align="right" style="border-bottom: 1px solid #e2e8f0;">Price</th>
                </tr>
                @foreach($topProducts as $product)
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="color: #1e293b; font-weight: 600;">{{ $product['name'] }}</td>
                        <td align="center" style="color: #64748b;">{{ $product['category'] ?? 'Supplements' }}</td>
                        <td align="center" style="color: #7c3aed; font-weight: 700;">{{ $product['matches'] ?? 24 }} matches</td>
                        <td align="right" style="color: #0f172a; font-weight: 600;">{{ $product['price'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <!-- Recent Client Interactions -->
    @if(!empty($recentInquiries))
        <div style="margin: 25px 0;">
            <h3 style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
                Recent Client Interactions
            </h3>
            <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; background: #ffffff;">
                @foreach($recentInquiries as $inquiry)
                    <div style="padding: 8px 0; border-bottom: 1px dashed #f1f5f9; display: flex; justify-content: space-between;">
                        <div>
                            <strong style="color: #1e293b; font-size: 13px;">{{ $inquiry['client_name'] }}</strong>
                            <span style="color: #64748b; font-size: 12px;"> - {{ $inquiry['topic'] ?? 'Supplement recommendation inquiry' }}</span>
                        </div>
                        <span style="color: #94a3b8; font-size: 11px;">{{ $inquiry['date'] ?? 'Recent' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Action Button -->
    <div class="btn-container" style="text-align: center; margin: 30px 0;">
        <a href="{{ $dashboardUrl }}" class="btn-primary" style="background-color: #1b1b18; color: #ffffff !important; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-block;">
            Open Supplier Dashboard
        </a>
    </div>

    <p style="font-size: 12px; color: #94a3b8; text-align: center; margin-top: 30px;">
        You are receiving this weekly email because you are a registered supplement supplier or business partner on BioVue Digital Wellness.
        To adjust your notification settings, visit your <a href="https://biovuedigitalwellness.com/settings/notifications" style="color: #6366f1;">Notification Preferences</a>.
    </p>

    <p style="font-size: 13px; color: #475569; margin-top: 20px;">
        Best regards,<br>
        <strong>The BioVue Digital Wellness Team</strong>
    </p>
@endsection
