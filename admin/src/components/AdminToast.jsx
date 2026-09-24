export default function AdminToast({ message, type = "success" }) {
  if (!message) {
    return null;
  }

  return (
    <div
      role="status"
      className={`gp-ppros-fixed gp-ppros-bottom-6 gp-ppros-right-6 gp-ppros-z-50 gp-ppros-max-w-sm gp-ppros-rounded-xl gp-ppros-px-4 gp-ppros-py-3 gp-ppros-text-sm gp-ppros-font-medium gp-ppros-shadow-lg ${
        type === "error" ? "gp-ppros-bg-red-600 gp-ppros-text-white" : "gp-ppros-bg-emerald-600 gp-ppros-text-white"
      }`}
    >
      {message}
    </div>
  );
}
