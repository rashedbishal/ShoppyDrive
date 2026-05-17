<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'meta_title' => 'Privacy Policy — ShoppyDrive',
                'meta_description' => 'Learn how ShoppyDrive collects, uses, and protects your personal information.',
                'content' => <<<'HTML'
<h2>Introduction</h2>
<p>Welcome to ShoppyDrive. We are committed to protecting your personal information and your right to privacy. This Privacy Policy explains how we collect, use, and safeguard your information when you visit our website or make a purchase.</p>

<h2>Information We Collect</h2>
<p>We collect information you provide directly to us, such as when you create an account, place an order, or contact us for support. This includes:</p>
<ul>
  <li>Name, email address, and password</li>
  <li>Billing and shipping address</li>
  <li>Phone number</li>
  <li>Payment information (processed securely; we do not store card details)</li>
  <li>Order history and preferences</li>
</ul>

<h2>How We Use Your Information</h2>
<p>We use the information we collect to:</p>
<ul>
  <li>Process and fulfil your orders</li>
  <li>Send order confirmations and shipping updates</li>
  <li>Respond to your comments and questions</li>
  <li>Send promotional communications (only if you opt in)</li>
  <li>Improve our website and services</li>
</ul>

<h2>Information Sharing</h2>
<p>We do not sell, trade, or rent your personal information to third parties. We may share your information with trusted service providers who assist us in operating our website and conducting our business, provided they agree to keep this information confidential.</p>

<h2>Data Security</h2>
<p>We implement appropriate technical and organisational measures to protect your personal information against unauthorised access, alteration, disclosure, or destruction. All transactions are secured with SSL encryption.</p>

<h2>Cookies</h2>
<p>We use cookies to enhance your browsing experience, remember your preferences, and analyse site traffic. You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent.</p>

<h2>Your Rights</h2>
<p>You have the right to access, correct, or delete your personal data at any time. To exercise these rights, please contact us at the email address listed on our contact page.</p>

<h2>Changes to This Policy</h2>
<p>We may update this Privacy Policy from time to time. We will notify you of any significant changes by posting the new policy on this page with an updated date.</p>

<h2>Contact Us</h2>
<p>If you have any questions about this Privacy Policy, please contact us through our <a href="/contact">contact page</a>.</p>
HTML,
                'is_published' => true,
            ],
            [
                'title' => 'Terms of Service',
                'slug' => 'terms',
                'meta_title' => 'Terms of Service — ShoppyDrive',
                'meta_description' => 'Read the terms and conditions governing your use of ShoppyDrive.',
                'content' => <<<'HTML'
<h2>Acceptance of Terms</h2>
<p>By accessing or using ShoppyDrive, you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our website.</p>

<h2>Use of the Website</h2>
<p>You agree to use ShoppyDrive only for lawful purposes and in a manner that does not infringe the rights of others. You must not:</p>
<ul>
  <li>Use the site in any fraudulent or unlawful way</li>
  <li>Transmit any unsolicited or unauthorised advertising material</li>
  <li>Attempt to gain unauthorised access to any part of the website</li>
  <li>Reproduce or redistribute content without permission</li>
</ul>

<h2>Account Responsibility</h2>
<p>You are responsible for maintaining the confidentiality of your account credentials. You agree to notify us immediately of any unauthorised use of your account. We are not liable for any loss resulting from unauthorised use of your account.</p>

<h2>Orders and Payments</h2>
<p>By placing an order, you confirm that the information you provide is accurate and complete. We reserve the right to cancel any order at our discretion, including if items are out of stock or if we suspect fraudulent activity. Payment must be completed before orders are dispatched.</p>

<h2>Pricing and Availability</h2>
<p>All prices are listed in Bangladeshi Taka (৳) and are subject to change without notice. We do not guarantee that all products are available at all times. In the event of a pricing error, we reserve the right to cancel affected orders and issue a full refund.</p>

<h2>Returns and Refunds</h2>
<p>We want you to be satisfied with your purchase. If you are not happy with your order, please contact us within 7 days of delivery to initiate a return or exchange. Items must be unused and in their original packaging.</p>

<h2>Limitation of Liability</h2>
<p>ShoppyDrive shall not be liable for any indirect, incidental, or consequential damages arising from your use of the website or the products purchased. Our total liability shall not exceed the amount paid for the specific order in dispute.</p>

<h2>Intellectual Property</h2>
<p>All content on this website, including text, images, logos, and graphics, is the property of ShoppyDrive and is protected by applicable intellectual property laws. You may not reproduce or distribute any content without prior written consent.</p>

<h2>Changes to Terms</h2>
<p>We reserve the right to modify these Terms of Service at any time. Continued use of the website after changes are posted constitutes your acceptance of the revised terms.</p>

<h2>Contact Us</h2>
<p>For questions about these Terms of Service, please reach out through our <a href="/contact">contact page</a>.</p>
HTML,
                'is_published' => true,
            ],
            [
                'title' => 'Frequently Asked Questions',
                'slug' => 'faq',
                'meta_title' => 'FAQ — ShoppyDrive',
                'meta_description' => 'Answers to the most common questions about shopping, delivery, returns, and more at ShoppyDrive.',
                'content' => <<<'HTML'
<h2>Orders &amp; Payments</h2>

<h3>How do I place an order?</h3>
<p>Browse our catalogue, add items to your cart, and proceed to checkout. Fill in your delivery details and confirm your order. You will receive an order confirmation via email or SMS shortly after.</p>

<h3>What payment methods do you accept?</h3>
<p>We currently accept <strong>Cash on Delivery (COD)</strong> for all orders across Bangladesh. Online payment options will be available soon.</p>

<h3>Can I modify or cancel my order after placing it?</h3>
<p>You can cancel or modify your order within 1 hour of placing it. Please contact our support team immediately. Once your order is dispatched, cancellations are no longer possible.</p>

<h2>Delivery</h2>

<h3>How long does delivery take?</h3>
<p>Standard delivery within Dhaka takes <strong>1–2 business days</strong>. Deliveries to other districts typically take <strong>3–5 business days</strong>.</p>

<h3>Is there a delivery charge?</h3>
<p>Delivery is <strong>free for orders over ৳999</strong>. A flat delivery fee applies to smaller orders, which is shown at checkout.</p>

<h3>Do you deliver nationwide?</h3>
<p>Yes! We deliver to all 64 districts across Bangladesh.</p>

<h2>Returns &amp; Refunds</h2>

<h3>What is your return policy?</h3>
<p>We accept returns within <strong>7 days of delivery</strong> for items that are unused, undamaged, and in their original packaging. Please contact us to initiate a return.</p>

<h3>How long does a refund take?</h3>
<p>Once we receive and inspect the returned item, refunds are processed within <strong>3–5 business days</strong>.</p>

<h3>What if I receive a damaged or wrong item?</h3>
<p>We sincerely apologise. Please contact us within 48 hours of receiving your order with a photo of the item, and we will arrange a replacement or full refund immediately.</p>

<h2>Account &amp; Support</h2>

<h3>Do I need an account to shop?</h3>
<p>No, you can checkout as a guest. However, creating an account lets you track orders, save addresses, and manage your wishlist more easily.</p>

<h3>How can I track my order?</h3>
<p>Log in to your account and visit the <strong>My Orders</strong> section to see real-time updates on your order status.</p>

<h3>How do I contact customer support?</h3>
<p>You can reach us via our <a href="/contact">contact page</a>, by phone, or by email. Our support team is available every day from 9 AM to 9 PM (BST).</p>
HTML,
                'is_published' => true,
            ],
        ];

        foreach ($pages as $data) {
            Page::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
