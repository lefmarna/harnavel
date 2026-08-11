export function getInitials(fullName: string): string {
  const [first, ...rest] = fullName.trim().split(/\s+/);

  if (!first) {
    return "";
  }

  const last = rest[rest.length - 1] ?? "";

  return `${first.charAt(0)}${last.charAt(0)}`.toUpperCase();
}
