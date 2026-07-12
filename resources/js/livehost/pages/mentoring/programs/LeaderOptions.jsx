/**
 * Renders the grouped <option> list for the "Program leader" <select>.
 * Live Host Desk staff (desk admin + assistants) are grouped under "Staff";
 * live hosts under "Live Hosts", with top-host-eligible hosts starred.
 */
export function LeaderOptions({ leaders }) {
  const list = leaders ?? [];
  const staff = list.filter((u) => u.is_staff);
  const hosts = list.filter((u) => !u.is_staff);

  return (
    <>
      <option value="">— No leader yet —</option>
      {staff.length > 0 && (
        <optgroup label="Staff">
          {staff.map((u) => (
            <option key={u.id} value={u.id}>
              {u.name} ({u.role_label})
            </option>
          ))}
        </optgroup>
      )}
      {hosts.length > 0 && (
        <optgroup label="Live Hosts">
          {hosts.map((u) => (
            <option key={u.id} value={u.id}>
              {u.name}
              {u.is_top_host_eligible ? ' ★' : ''}
            </option>
          ))}
        </optgroup>
      )}
    </>
  );
}
