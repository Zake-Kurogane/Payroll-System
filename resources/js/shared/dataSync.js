const ATTENDANCE_KEY = "attendance_updated_at";
const EMPLOYEES_KEY = "employees_updated_at";
const EVENT_NAME = "dataSync";

export { ATTENDANCE_KEY, EMPLOYEES_KEY };

export function getAttendanceUpdatedAt() {
  try {
    return Number(localStorage.getItem(ATTENDANCE_KEY) || 0);
  } catch {
    return 0;
  }
}

export function getEmployeesUpdatedAt() {
  try {
    return Number(localStorage.getItem(EMPLOYEES_KEY) || 0);
  } catch {
    return 0;
  }
}

export function broadcastAttendanceUpdate() {
  try {
    localStorage.setItem(ATTENDANCE_KEY, String(Date.now()));
  } catch {
    // ignore
  }
  try {
    window.dispatchEvent(new CustomEvent(EVENT_NAME, { detail: { type: "attendance" } }));
  } catch {
    // ignore
  }
}

export function broadcastEmployeeUpdate() {
  try {
    localStorage.setItem(EMPLOYEES_KEY, String(Date.now()));
  } catch {
    // ignore
  }
  try {
    window.dispatchEvent(new CustomEvent(EVENT_NAME, { detail: { type: "employees" } }));
  } catch {
    // ignore
  }
}

export function initDataSync({ onAttendance, onEmployees } = {}) {
  window.addEventListener("storage", (e) => {
    if (e.key === ATTENDANCE_KEY && typeof onAttendance === "function") onAttendance();
    if (e.key === EMPLOYEES_KEY && typeof onEmployees === "function") onEmployees();
  });
  window.addEventListener(EVENT_NAME, (e) => {
    const type = e?.detail?.type;
    if (type === "attendance" && typeof onAttendance === "function") onAttendance();
    if (type === "employees" && typeof onEmployees === "function") onEmployees();
  });
}
