export default function AdminToast({ message, type = "success" }) {
  if (!message) {
    return null;
  }

  return (
    <div
      role="status"
      className={`myrvento-fixed myrvento-bottom-6 myrvento-right-6 myrvento-z-50 myrvento-max-w-sm myrvento-rounded-xl myrvento-px-4 myrvento-py-3 myrvento-text-sm myrvento-font-medium myrvento-shadow-lg ${
        type === "error" ? "myrvento-bg-red-600 myrvento-text-white" : "myrvento-bg-emerald-600 myrvento-text-white"
      }`}
    >
      {message}
    </div>
  );
}
