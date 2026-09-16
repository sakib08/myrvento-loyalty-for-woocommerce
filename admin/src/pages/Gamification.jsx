import { useCallback, useEffect, useState } from "react";
import { api } from "../api";
import AdminToast from "../components/AdminToast";
import { Button, Card, Field, Toggle, inputClass } from "../components/FormFields";

export default function Gamification() {
  const [badges, setBadges] = useState([]);
  const [challenges, setChallenges] = useState([]);
  const [board, setBoard] = useState([]);
  const [toast, setToast] = useState(null);
  const [badgeDraft, setBadgeDraft] = useState({
    name: "",
    milestone_type: "orders",
    milestone_value: 1,
    points_bonus: 0,
    enabled: true,
  });
  const [challengeDraft, setChallengeDraft] = useState({
    name: "",
    type: "orders",
    target_value: 3,
    points_reward: 50,
    enabled: true,
  });

  const showToast = useCallback((message, type = "success") => {
    setToast({ message, type });
    window.setTimeout(() => setToast(null), 3000);
  }, []);

  const load = useCallback(async () => {
    try {
      const [badgeRows, challengeRows, leaders] = await Promise.all([
        api.getBadges(),
        api.getChallenges(),
        api.getLeaderboard(),
      ]);
      setBadges(badgeRows);
      setChallenges(challengeRows);
      setBoard(leaders);
    } catch (err) {
      showToast(err.message, "error");
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  async function saveBadge(badge) {
    try {
      if (badge.id) await api.updateBadge(badge.id, badge);
      else {
        await api.createBadge(badge);
        setBadgeDraft({ name: "", milestone_type: "orders", milestone_value: 1, points_bonus: 0, enabled: true });
      }
      await load();
      showToast("Badge saved.");
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  async function saveChallenge(challenge) {
    try {
      if (challenge.id) await api.updateChallenge(challenge.id, challenge);
      else {
        await api.createChallenge(challenge);
        setChallengeDraft({ name: "", type: "orders", target_value: 3, points_reward: 50, enabled: true });
      }
      await load();
      showToast("Challenge saved.");
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  return (
    <div className="gp-grid gp-gap-5">
      <AdminToast message={toast?.message} type={toast?.type} />

      <Card title="Loyalty leaderboard" description="Ranked by lifetime points earned.">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Customer</th>
              <th>Lifetime</th>
              <th>Available</th>
              <th>Tier</th>
            </tr>
          </thead>
          <tbody>
            {board.map((row) => (
              <tr key={row.customer_id}>
                <td>{row.rank}</td>
                <td>{row.name}<div className="gp-text-xs gp-text-slate-500">{row.email}</div></td>
                <td>{row.lifetime_earned}</td>
                <td>{row.available}</td>
                <td style={{ color: row.tier_color }}>{row.tier_name || "—"}</td>
              </tr>
            ))}
            {board.length === 0 && <tr><td colSpan="5" className="gp-text-slate-500">No earners yet.</td></tr>}
          </tbody>
        </table>
      </Card>

      <Card title="Achievement badges">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Milestone</th>
              <th>Value</th>
              <th>Bonus</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {badges.map((badge) => (
              <tr key={badge.id}>
                <td><input className={inputClass} value={badge.name} onChange={(e) => setBadges((c) => c.map((b) => b.id === badge.id ? { ...b, name: e.target.value } : b))} /></td>
                <td>
                  <select className={inputClass} value={badge.milestone_type} onChange={(e) => setBadges((c) => c.map((b) => b.id === badge.id ? { ...b, milestone_type: e.target.value } : b))}>
                    <option value="orders">Orders</option>
                    <option value="spend">Spending</option>
                    <option value="reviews">Reviews</option>
                    <option value="referrals">Referrals</option>
                    <option value="points">Points</option>
                    <option value="streak">Streak</option>
                  </select>
                </td>
                <td><input className={inputClass} type="number" value={badge.milestone_value} onChange={(e) => setBadges((c) => c.map((b) => b.id === badge.id ? { ...b, milestone_value: Number(e.target.value) } : b))} /></td>
                <td><input className={inputClass} type="number" value={badge.points_bonus} onChange={(e) => setBadges((c) => c.map((b) => b.id === badge.id ? { ...b, points_bonus: Number(e.target.value) } : b))} /></td>
                <td className="gp-flex gp-gap-2">
                  <Button onClick={() => saveBadge(badge)}>Save</Button>
                  <Button variant="danger" onClick={async () => { await api.deleteBadge(badge.id); load(); }}>Delete</Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="gp-mt-4 gp-grid gp-gap-3 md:gp-grid-cols-5 md:gp-items-end">
          <Field label="Badge name"><input className={inputClass} value={badgeDraft.name} onChange={(e) => setBadgeDraft({ ...badgeDraft, name: e.target.value })} /></Field>
          <Field label="Type">
            <select className={inputClass} value={badgeDraft.milestone_type} onChange={(e) => setBadgeDraft({ ...badgeDraft, milestone_type: e.target.value })}>
              <option value="orders">Orders</option>
              <option value="spend">Spending</option>
              <option value="reviews">Reviews</option>
              <option value="referrals">Referrals</option>
              <option value="points">Points</option>
              <option value="streak">Streak</option>
            </select>
          </Field>
          <Field label="Value"><input className={inputClass} type="number" value={badgeDraft.milestone_value} onChange={(e) => setBadgeDraft({ ...badgeDraft, milestone_value: Number(e.target.value) })} /></Field>
          <Field label="Bonus points"><input className={inputClass} type="number" value={badgeDraft.points_bonus} onChange={(e) => setBadgeDraft({ ...badgeDraft, points_bonus: Number(e.target.value) })} /></Field>
          <Button onClick={() => saveBadge(badgeDraft)}>Add badge</Button>
        </div>
      </Card>

      <Card title="Challenges" description="Limited-time goals with a progress bar on My Account.">
        {challenges.map((challenge) => (
          <div key={challenge.id} className="gp-mb-3 gp-grid gp-gap-3 gp-rounded-xl gp-border gp-border-slate-100 gp-p-4 md:gp-grid-cols-6 md:gp-items-end">
            <Field label="Name"><input className={inputClass} value={challenge.name} onChange={(e) => setChallenges((c) => c.map((row) => row.id === challenge.id ? { ...row, name: e.target.value } : row))} /></Field>
            <Field label="Type">
              <select className={inputClass} value={challenge.type} onChange={(e) => setChallenges((c) => c.map((row) => row.id === challenge.id ? { ...row, type: e.target.value } : row))}>
                <option value="orders">Orders</option>
                <option value="spend">Spending</option>
                <option value="reviews">Reviews</option>
                <option value="referrals">Referrals</option>
                <option value="streak">Streak</option>
              </select>
            </Field>
            <Field label="Target"><input className={inputClass} type="number" value={challenge.target_value} onChange={(e) => setChallenges((c) => c.map((row) => row.id === challenge.id ? { ...row, target_value: Number(e.target.value) } : row))} /></Field>
            <Field label="Reward points"><input className={inputClass} type="number" value={challenge.points_reward} onChange={(e) => setChallenges((c) => c.map((row) => row.id === challenge.id ? { ...row, points_reward: Number(e.target.value) } : row))} /></Field>
            <Toggle label="Enabled" checked={challenge.enabled} onChange={(enabled) => saveChallenge({ ...challenge, enabled })} />
            <div className="gp-flex gp-gap-2">
              <Button onClick={() => saveChallenge(challenge)}>Save</Button>
              <Button variant="danger" onClick={async () => { await api.deleteChallenge(challenge.id); load(); }}>Delete</Button>
            </div>
          </div>
        ))}
        <div className="gp-grid gp-gap-3 md:gp-grid-cols-5 md:gp-items-end">
          <Field label="Challenge"><input className={inputClass} value={challengeDraft.name} onChange={(e) => setChallengeDraft({ ...challengeDraft, name: e.target.value })} /></Field>
          <Field label="Type">
            <select className={inputClass} value={challengeDraft.type} onChange={(e) => setChallengeDraft({ ...challengeDraft, type: e.target.value })}>
              <option value="orders">Orders</option>
              <option value="spend">Spending</option>
              <option value="referrals">Referrals</option>
            </select>
          </Field>
          <Field label="Target"><input className={inputClass} type="number" value={challengeDraft.target_value} onChange={(e) => setChallengeDraft({ ...challengeDraft, target_value: Number(e.target.value) })} /></Field>
          <Field label="Points"><input className={inputClass} type="number" value={challengeDraft.points_reward} onChange={(e) => setChallengeDraft({ ...challengeDraft, points_reward: Number(e.target.value) })} /></Field>
          <Button onClick={() => saveChallenge(challengeDraft)}>Add challenge</Button>
        </div>
      </Card>
    </div>
  );
}
