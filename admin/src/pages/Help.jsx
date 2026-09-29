import { useMemo, useState } from "react";
import { adminConfig } from "../api";
import { Card } from "../components/FormFields";

const TABS = [
  { id: "start", label: "Getting started" },
  { id: "loyalty", label: "Loyalty" },
  { id: "sales", label: "Sales" },
  { id: "measure", label: "Analytics & AI" },
  { id: "account", label: "Customer account" },
];

function topics(urls) {
  return [
    {
      id: "overview",
      tab: "start",
      title: "What Myrvento Loyalty for WooCommerce does",
      paragraphs: [
        "Myrvento Loyalty for WooCommerce is a WooCommerce loyalty and growth toolkit. Purchases, reviews, signups, birthdays, social shares, campaigns, and referrals all write to one points ledger. There is no separate referral wallet and no cash payout.",
        "Sales, operations, analytics, and revenue screens read WooCommerce orders. The AI models run on the store. An optional language model only rewrites Myrvento Brain wording. Suggested prices are never applied to products.",
      ],
    },
    {
      id: "requirements",
      tab: "start",
      title: "Requirements",
      items: [
        "WooCommerce must be active. Myrvento Loyalty for WooCommerce follows High-Performance Order Storage.",
        "Shop managers need the manage WooCommerce capability.",
        "WordPress 6.0 or newer, and PHP 7.4 or newer.",
        "WooCommerce Subscriptions is optional. Operations shows an empty note when it is not active.",
      ],
    },
    {
      id: "first",
      tab: "start",
      title: "First setup",
      items: [
        "Open Dashboard for the last 30 days of revenue, abandoned carts, win-back customers, and the forecast.",
        "Open Points and confirm earn rules for purchases, reviews, signups, first orders, birthdays, and social shares.",
        "Add VIP tiers, rewards, and a referral campaign before sending customers to My Account.",
        "Open Settings for the points name, the order status that earns points, referral cookies, and AI.",
      ],
      links: [
        { href: urls.dashboard, label: "Dashboard" },
        { href: urls.points, label: "Points" },
        { href: urls.settings, label: "Settings" },
      ],
    },
    {
      id: "empty",
      tab: "start",
      title: "Empty screens",
      paragraphs: [
        "A new store with no paid orders shows empty states. That is expected. Reports count orders in WooCommerce paid statuses. Drafts and unpaid checkouts stay out of revenue.",
      ],
    },
    {
      id: "ledger",
      tab: "loyalty",
      title: "One points balance",
      paragraphs: [
        "Every earn and redeem writes the same customer balance. Adjusting points, reversing a refunded order, and paying a referral bonus all use that ledger.",
        "Points are awarded when an order reaches the status in Settings. Completed is the default. Processing is the other choice. Cancelling or refunding that order reverses the points from it.",
      ],
      links: [{ href: urls.points, label: "Points" }],
    },
    {
      id: "earn",
      tab: "loyalty",
      title: "Ways to earn",
      items: [
        "Purchase: points per 1 unit of order subtotal. Product and category bonuses stack on that rate.",
        "First purchase: a one-time bonus on the first qualifying order.",
        "Review: awarded when a product review is approved.",
        "Signup: welcome points when a customer account is created.",
        "Birthday: once per year. The customer saves a month and day on My Account.",
        "Social: awarded when a customer shares a referral link. Settings can limit this to once per customer.",
      ],
      paragraphs: [
        "Set expiration days in Settings. Zero means points do not expire.",
      ],
    },
    {
      id: "tiers",
      tab: "loyalty",
      title: "VIP tiers",
      paragraphs: [
        "A tier qualifies on spending, order count, or lifetime points. One tier can be the default for everyone else. Benefits are listed on the customer’s Loyalty page.",
        "When downgrades are on, a customer who falls below the qualifier can move down after the window in Settings. The default window is 365 days. Turn downgrades off to let customers keep a higher tier.",
      ],
      links: [{ href: urls.tiers, label: "VIP Tiers" }],
    },
    {
      id: "rewards",
      tab: "loyalty",
      title: "Rewards",
      paragraphs: [
        "A reward costs points and can be limited to a tier or a stock count. Types include a percentage coupon, a fixed coupon, free shipping, a free product, exclusive or early access, special pricing, and a VIP-only offer.",
        "Redeeming spends points and records the redemption. It does not change the product’s regular price.",
      ],
      links: [{ href: urls.rewards, label: "Rewards" }],
    },
    {
      id: "play",
      tab: "loyalty",
      title: "Badges and challenges",
      paragraphs: [
        "Badges mark achievements. Challenges track progress toward a goal and can award points when finished. Customers see both on My Account → Loyalty, next to their balance and history.",
      ],
      links: [{ href: urls.gamification, label: "Gamification" }],
    },
    {
      id: "referrals",
      tab: "sales",
      title: "Referrals",
      paragraphs: [
        "A campaign sets the points for the referrer and the friend they invite. The share link uses the query parameter in Settings. The default is gp_ref. A visitor cookie remembers the code for the cookie length in Settings. The default is 30 days.",
        "When the referred customer pays, both rewards credit the same points ledger. Referral reporting is under Referrals and under Analytics → Marketing.",
      ],
      links: [{ href: urls.referrals, label: "Referrals" }],
    },
    {
      id: "conversion",
      tab: "sales",
      title: "Sales and conversion",
      items: [
        "Abandoned cart lists sessions from the last 30 days that added a product or started checkout and did not purchase.",
        "Upsell and cross-sell come from products bought together, plus the upsell and cross-sell links already set on products.",
        "Recovery lists customers who have not ordered for 60 days or more. After 180 days the suggested action is a win-back offer. Before that it is a loyalty reminder.",
      ],
      paragraphs: [
        "These screens are queues for the shop. Myrvento Loyalty for WooCommerce does not email the customer from them.",
      ],
      links: [{ href: urls.sales, label: "Sales" }],
    },
    {
      id: "operations",
      tab: "sales",
      title: "Operations",
      items: [
        "Orders shows status counts and the latest orders.",
        "Subscriptions reads WooCommerce Subscriptions when that plugin is active.",
        "Coupons lists shop coupons.",
        "Customer activity merges recent storefront events with the points ledger.",
      ],
      links: [{ href: urls.operations, label: "Operations" }],
    },
    {
      id: "analytics",
      tab: "measure",
      title: "Analytics",
      paragraphs: [
        "Ecommerce covers revenue, orders, average order value, and the trend. Customers covers cohorts, segments, and churn. Products covers units and profit. Marketing covers campaign clicks, signups, and referral revenue.",
        "Use the date range on that screen. Figures follow WooCommerce paid statuses.",
      ],
      links: [{ href: urls.analytics, label: "Analytics" }],
    },
    {
      id: "revenue",
      tab: "measure",
      title: "Revenue intelligence",
      items: [
        "LTV is the average value of a customer.",
        "Retention shows how many customers place a second and third order, and the typical days between orders.",
        "Churn splits customers into active, at risk, and churned.",
        "Attribution compares first-touch and last-touch sources.",
        "Profitability uses product cost when WooCommerce has it. Profit stays blank when cost is missing.",
        "Forecasting reuses the on-store demand model when AI is enabled.",
      ],
      links: [{ href: urls.revenue, label: "Revenue" }],
    },
    {
      id: "ai",
      tab: "measure",
      title: "Myrvento Brain",
      paragraphs: [
        "Predictions score churn risk, likely next purchase, and high-value customers from orders already in WooCommerce. Pricing suggests a price or discount. Forecasting estimates seasonal demand and inventory. None of these write back to a product or an order.",
        "Turn AI off in Settings to stop new runs. Results are cached for about six hours.",
        "Optional narration only rewrites card text, and it uses the WordPress AI Client. The plugin does not store a provider key. Leave narration off to keep the on-store wording.",
      ],
      links: [
        { href: urls.ai, label: "AI" },
        { href: urls.settings, label: "Settings" },
      ],
    },
    {
      id: "myaccount",
      tab: "account",
      title: "My Account",
      paragraphs: [
        "Logged-in customers get Loyalty and Referrals links after Orders. Labels come from Settings. Loyalty shows the balance, tier, rewards, badges, challenges, birthday, and history. Referrals shows the share code, link, and referral history.",
      ],
    },
    {
      id: "shortcodes",
      tab: "account",
      title: "Shortcodes",
      items: [
        "[ciwp_loyalty] prints the loyalty account. Guests are asked to log in.",
        "[ciwp_referral] prints the share code and history. Guests are asked to log in.",
      ],
    },
    {
      id: "permalinks",
      tab: "account",
      title: "If My Account links 404",
      paragraphs: [
        "Loyalty and Referrals are WooCommerce account endpoints. Activating the plugin refreshes permalinks. If those links still 404, open Settings → Permalinks and save once.",
      ],
    },
  ];
}

function matches(topic, query) {
  if (!query) {
    return true;
  }
  const haystack = [topic.title, ...(topic.paragraphs || []), ...(topic.items || [])]
    .join(" ")
    .toLowerCase();
  return haystack.includes(query);
}

function Topic({ topic }) {
  return (
    <Card
      title={topic.title}
      actions={
        topic.links?.length ? (
          <div className="ciwp-flex ciwp-flex-wrap ciwp-gap-2">
            {topic.links.map((link) => (
              <a
                key={link.href}
                href={link.href}
                className="ciwp-rounded-lg ciwp-bg-brand-50 ciwp-px-3 ciwp-py-1.5 ciwp-text-sm ciwp-font-semibold ciwp-text-brand-700 ciwp-no-underline hover:ciwp-bg-brand-100"
              >
                {link.label}
              </a>
            ))}
          </div>
        ) : null
      }
    >
      <div className="ciwp-grid ciwp-gap-3">
        {(topic.paragraphs || []).map((paragraph) => (
          <p key={paragraph} className="ciwp-m-0 ciwp-text-sm ciwp-leading-6 ciwp-text-slate-600">
            {paragraph}
          </p>
        ))}
        {topic.items?.length ? (
          <ul className="ciwp-m-0 ciwp-list-disc ciwp-space-y-1 ciwp-pl-5 ciwp-text-sm ciwp-leading-6 ciwp-text-slate-600">
            {topic.items.map((item) => (
              <li key={item}>{item}</li>
            ))}
          </ul>
        ) : null}
      </div>
    </Card>
  );
}

export default function Help() {
  const [tab, setTab] = useState("start");
  const [query, setQuery] = useState("");
  const urls = adminConfig.urls || {};
  const version = adminConfig.version || "";
  const needle = query.trim().toLowerCase();
  const all = useMemo(() => topics(urls), [urls]);
  const visible = all.filter((topic) => (needle ? matches(topic, needle) : topic.tab === tab));

  return (
    <div className="ciwp-grid ciwp-gap-5">
      <div className="ciwp-flex ciwp-flex-wrap ciwp-items-end ciwp-justify-between ciwp-gap-4">
        <div>
          <h2 className="ciwp-m-0 ciwp-text-xl ciwp-font-bold ciwp-text-slate-900">Help</h2>
          <p className="ciwp-m-0 ciwp-mt-1 ciwp-text-sm ciwp-text-slate-500">
            How loyalty, sales, analytics, and the customer account fit together
            {version ? ` · Myrvento Loyalty for WooCommerce ${version}` : ""}.
          </p>
        </div>
        <label className="ciwp-block ciwp-min-w-[16rem] ciwp-flex-1 md:ciwp-max-w-sm">
          <span className="ciwp-sr-only">Search help</span>
          <input
            className="ciwp-w-full ciwp-rounded-lg ciwp-border ciwp-border-slate-200 ciwp-bg-white ciwp-px-3 ciwp-py-2 ciwp-text-sm focus:ciwp-border-brand-400 focus:ciwp-outline-none focus:ciwp-ring-2 focus:ciwp-ring-brand-100"
            type="search"
            value={query}
            placeholder="Search help"
            onChange={(event) => setQuery(event.target.value)}
          />
        </label>
      </div>

      <div className="ciwp-flex ciwp-flex-wrap ciwp-gap-1 ciwp-rounded-xl ciwp-bg-slate-100 ciwp-p-1">
        {TABS.map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => {
              setTab(item.id);
              setQuery("");
            }}
            className={`ciwp-rounded-lg ciwp-border-0 ciwp-px-3 ciwp-py-2 ciwp-text-sm ciwp-font-semibold ciwp-transition ${
              !needle && tab === item.id
                ? "ciwp-bg-white ciwp-text-brand-700 ciwp-shadow-sm"
                : "ciwp-bg-transparent ciwp-text-slate-600 hover:ciwp-text-slate-900"
            }`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {visible.length ? (
        <div className="ciwp-grid ciwp-gap-4">
          {visible.map((topic) => (
            <Topic key={topic.id} topic={topic} />
          ))}
        </div>
      ) : (
        <Card title="No matching topics">
          <p className="ciwp-m-0 ciwp-text-sm ciwp-text-slate-500">
            Try points, referral, churn, or shortcode.
          </p>
        </Card>
      )}
    </div>
  );
}
