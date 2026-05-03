@php $brand = \App\Models\PlatformSetting::get('platform_name', 'XForce'); @endphp
@php $email = 'support@xforcehr.com'; @endphp

<p>
    These Terms of Service (the &ldquo;<strong>Terms</strong>&rdquo;) govern your access to and use of
    <strong>{{ $brand }}</strong> (the &ldquo;<strong>Service</strong>&rdquo;).
    By signing up for, accessing, or using the Service, you agree to be bound by these Terms.
    If you are accepting these Terms on behalf of an organisation, you represent that you have authority
    to bind that organisation.
</p>

<h2 id="definitions" class="toc-anchor">1. Definitions</h2>
<ul>
    <li><strong>Customer</strong> &mdash; the company that subscribes to the Service to manage its workforce.</li>
    <li><strong>User</strong> &mdash; any individual authorised by the Customer to use the Service (administrators, HR managers, employees, owners).</li>
    <li><strong>Employee</strong> &mdash; an individual whose HR data is processed in the Service by the Customer.</li>
    <li><strong>Customer Data</strong> &mdash; all data the Customer or its Users upload to or generate in the Service, including employee records, attendance, time-off, payroll, loans, and uploaded documents.</li>
    <li><strong>Subscription</strong> &mdash; the paid plan that grants the Customer access to the Service.</li>
</ul>

<h2 id="eligibility" class="toc-anchor">2. Account registration and eligibility</h2>
<ul>
    <li>You must be at least 18 years old and legally able to enter binding contracts.</li>
    <li>The information you provide during signup must be accurate and complete, and you must keep it up to date.</li>
    <li>You are responsible for safeguarding your credentials and for all activity that occurs under your account.</li>
    <li>You must notify us promptly of any unauthorised access or breach of your account.</li>
</ul>

<h2 id="subscription-billing" class="toc-anchor">3. Subscription and billing</h2>
<ul>
    <li>Plans, fees, and billing cycles are described on our pricing page or in your order form.</li>
    <li>Fees are quoted in the currency shown on the pricing page or your order form, and exclude applicable taxes. Any value-added tax, sales tax, or equivalent is added based on your jurisdiction and applicable law.</li>
    <li>Subscriptions <strong>auto-renew</strong> at the end of each billing cycle unless cancelled before the renewal date.</li>
    <li>If a payment fails, we will retry and notify you. After a 7-day grace period, we may suspend access until payment is received.</li>
    <li>Except where required by law, fees paid are <strong>non-refundable</strong>; if you cancel mid-cycle, your subscription remains active until the end of the paid period.</li>
    <li>We may change pricing for future billing cycles with at least 30 days&rsquo; notice; existing renewals run at the previously agreed rate.</li>
</ul>

<h2 id="acceptable-use" class="toc-anchor">4. Acceptable use</h2>
<p>You agree not to:</p>
<ul>
    <li>Reverse-engineer, scrape, copy, or resell any part of the Service.</li>
    <li>Use the Service to upload or transmit unlawful, infringing, or harmful content.</li>
    <li>Attempt to bypass authentication, tenant isolation, rate limits, or any security mechanism.</li>
    <li>Use the Service in a way that interferes with other Customers or that overloads our infrastructure.</li>
    <li>Share Service credentials with parties who are not authorised Users of your organisation.</li>
</ul>

<h2 id="customer-data" class="toc-anchor">5. Customer Data and ownership</h2>
<ul>
    <li>You retain all rights, title, and interest in Customer Data.</li>
    <li>You grant us a limited, non-exclusive, royalty-free licence to host, process, copy, transmit, and display Customer Data solely as needed to provide the Service.</li>
    <li>{{ $brand }} acts as a <strong>data processor</strong> for Customer Data and processes it under the terms of our Data Processing Agreement and our <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</li>
    <li>You can export Customer Data via the in-app export tools or by contacting support for a structured data dump.</li>
</ul>

<h2 id="employer-responsibilities" class="toc-anchor">6. Employer responsibilities</h2>
<p>
    As the data controller for your employees&rsquo; HR records, you are responsible for:
</p>
<ul>
    <li>Notifying employees in line with applicable law that their data is being processed in {{ $brand }}.</li>
    <li>Obtaining any consents required by Egyptian Law&nbsp;151/2020 or other applicable regimes for the data you upload.</li>
    <li>Configuring access controls (roles, branches, permissions) appropriately for your organisation.</li>
    <li>Honouring employees&rsquo; data-subject requests (access, correction, deletion, portability) within the legal timeframes.</li>
    <li>Retaining or deleting records in line with Egyptian Labor Law, Social Insurance Law, and Tax Law.</li>
</ul>

<h2 id="intellectual-property" class="toc-anchor">7. Intellectual property</h2>
<ul>
    <li>{{ $brand }} owns all right, title, and interest in the Service, including the underlying software, brand, templates, and documentation.</li>
    <li>You receive a non-exclusive, non-transferable right to use the Service during your active Subscription, subject to these Terms.</li>
    <li>You retain ownership of your logos, brand assets, and uploaded content; you grant us a licence to display them only as needed to render your Customer-facing pages.</li>
</ul>

<h2 id="availability" class="toc-anchor">8. Availability and updates</h2>
<ul>
    <li>We work to make the Service available on a continuous basis, but do not commit to a specific uptime percentage unless agreed in writing in a separate service-level addendum.</li>
    <li>Planned maintenance is announced in advance where practicable; emergency maintenance may occur without prior notice.</li>
    <li>We may release updates, fixes, and new features that change the Service&rsquo;s appearance or behaviour. We will not remove material features without reasonable notice.</li>
</ul>

<h2 id="warranties" class="toc-anchor">9. Warranties and disclaimers</h2>
<p>
    The Service is provided on an &ldquo;<strong>as is</strong>&rdquo; and &ldquo;<strong>as available</strong>&rdquo; basis.
    Except as expressly set out in these Terms or in a written agreement with us, {{ $brand }} makes no warranties of any kind,
    whether express, implied, statutory, or otherwise, including any warranties of merchantability, fitness for a particular
    purpose, non-infringement, or that the Service will be uninterrupted or error-free. {{ $brand }} is a record-keeping and
    operational platform; it is not a substitute for professional legal, accounting, or HR advice, and the Customer remains
    responsible for compliance with applicable employment, payroll, and tax laws.
</p>

<h2 id="liability" class="toc-anchor">10. Limitation of liability</h2>
<p>
    To the maximum extent permitted by law, neither party will be liable for any indirect, incidental, special,
    consequential, or punitive damages, or for loss of profits, revenue, data, or goodwill, arising out of or in
    connection with these Terms or the Service.
</p>
<p>
    {{ $brand }}&rsquo;s total aggregate liability under these Terms in any 12-month period is capped at the fees you
    paid us during that period.
</p>

<h2 id="indemnification" class="toc-anchor">11. Indemnification</h2>
<ul>
    <li><strong>By you:</strong> You will defend and indemnify {{ $brand }} against any third-party claim arising from your misuse of the Service, your Customer Data, or your breach of these Terms.</li>
    <li><strong>By us:</strong> {{ $brand }} will defend and indemnify you against any third-party claim that the Service, as provided, infringes that party&rsquo;s intellectual-property rights, provided you notify us promptly and let us control the defence.</li>
</ul>

<h2 id="suspension-termination" class="toc-anchor">12. Suspension and termination</h2>
<ul>
    <li>You can cancel your Subscription at any time from your billing settings or by contacting support.</li>
    <li>We may suspend or terminate your access if you materially breach these Terms, fail to pay, or use the Service in a way that puts other Customers or our infrastructure at risk.</li>
    <li>On termination, you have <strong>30 days</strong> to export your Customer Data; after that period we may delete it, subject to retention obligations described in the Privacy Policy.</li>
</ul>

<h2 id="changes" class="toc-anchor">13. Changes to these Terms</h2>
<p>
    We may revise these Terms from time to time. Material changes will be communicated to active Customer
    administrators by email at least 30 days before they take effect. Your continued use of the Service after
    the effective date constitutes acceptance of the revised Terms.
</p>

<h2 id="governing-law" class="toc-anchor">14. Governing law and disputes</h2>
<p>
    These Terms are governed by the laws of the <strong>Arab Republic of Egypt</strong>. Any dispute arising out of or
    in connection with these Terms is subject to the exclusive jurisdiction of the competent courts in <strong>Cairo, Egypt</strong>.
</p>

<h2 id="contact" class="toc-anchor">15. Contact</h2>
<p>
    Questions about these Terms:
    <a href="mailto:{{ $email }}">{{ $email }}</a>.
</p>
