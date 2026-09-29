const config = window.myrventoAdmin ?? {
  apiUrl: "/wp-json/myrvento/v1/",
  nonce: "",
  urls: {},
  i18n: {},
};

async function request(path, options = {}) {
  const res = await fetch(`${config.apiUrl}${path}`, {
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": config.nonce,
    },
    credentials: "same-origin",
    ...options,
  });

  const body = await res.json().catch(() => ({}));

  if (!res.ok) {
    throw new Error(body.message || `Request failed (${res.status})`);
  }

  return body;
}

export const api = {
  getSettings: () => request("settings"),
  saveSettings: (settings) =>
    request("settings", { method: "POST", body: JSON.stringify(settings) }),
  getRules: () => request("point-rules"),
  createRule: (data) =>
    request("point-rules", { method: "POST", body: JSON.stringify(data) }),
  updateRule: (id, data) =>
    request(`point-rules/${id}`, { method: "POST", body: JSON.stringify(data) }),
  deleteRule: (id) => request(`point-rules/${id}`, { method: "DELETE" }),
  adjustPoints: (data) =>
    request("points/adjust", { method: "POST", body: JSON.stringify(data) }),
  getCustomers: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return request(`customers${query ? `?${query}` : ""}`);
  },
  getCustomer: (id) => request(`customers/${id}`),
  getTiers: () => request("tiers"),
  createTier: (data) =>
    request("tiers", { method: "POST", body: JSON.stringify(data) }),
  updateTier: (id, data) =>
    request(`tiers/${id}`, { method: "POST", body: JSON.stringify(data) }),
  deleteTier: (id) => request(`tiers/${id}`, { method: "DELETE" }),
  getRewards: () => request("rewards"),
  createReward: (data) =>
    request("rewards", { method: "POST", body: JSON.stringify(data) }),
  updateReward: (id, data) =>
    request(`rewards/${id}`, { method: "POST", body: JSON.stringify(data) }),
  deleteReward: (id) => request(`rewards/${id}`, { method: "DELETE" }),
  getRedemptions: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return request(`redemptions${query ? `?${query}` : ""}`);
  },
  getBadges: () => request("badges"),
  createBadge: (data) =>
    request("badges", { method: "POST", body: JSON.stringify(data) }),
  updateBadge: (id, data) =>
    request(`badges/${id}`, { method: "POST", body: JSON.stringify(data) }),
  deleteBadge: (id) => request(`badges/${id}`, { method: "DELETE" }),
  getChallenges: () => request("challenges"),
  createChallenge: (data) =>
    request("challenges", { method: "POST", body: JSON.stringify(data) }),
  updateChallenge: (id, data) =>
    request(`challenges/${id}`, { method: "POST", body: JSON.stringify(data) }),
  deleteChallenge: (id) => request(`challenges/${id}`, { method: "DELETE" }),
  getLeaderboard: () => request("leaderboard?limit=25"),
  getCampaigns: () => request("referral-campaigns"),
  createCampaign: (data) =>
    request("referral-campaigns", { method: "POST", body: JSON.stringify(data) }),
  updateCampaign: (id, data) =>
    request(`referral-campaigns/${id}`, { method: "POST", body: JSON.stringify(data) }),
  deleteCampaign: (id) => request(`referral-campaigns/${id}`, { method: "DELETE" }),
  getReferrals: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return request(`referrals${query ? `?${query}` : ""}`);
  },
  getClicks: () => request("referral-clicks"),
  searchProducts: (search) =>
    request(`catalog/products?search=${encodeURIComponent(search || "")}`),
  searchCategories: (search) =>
    request(`catalog/categories?search=${encodeURIComponent(search || "")}`),
  getAnalytics: (report, params = {}) => {
    const query = new URLSearchParams(params).toString();
    return request(`analytics/${report}${query ? `?${query}` : ""}`);
  },
  getDashboard: () => request("dashboard"),
  getSales: () => request("sales"),
  getOperations: () => request("operations"),
  getAI: (report) => request(`ai/${report}`),
  refreshAI: () => request("ai/refresh", { method: "POST", body: "{}" }),
  narrateAI: () => request("ai/narrate", { method: "POST", body: "{}" }),
  applyAIPrice: (data) =>
    request("ai/pricing/apply", { method: "POST", body: JSON.stringify(data) }),
};

export const adminConfig = config;
