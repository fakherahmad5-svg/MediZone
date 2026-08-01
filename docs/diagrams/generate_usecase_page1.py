#!/usr/bin/env python3
"""Generate VMC Use Case Diagram — Page 1 (System Overview) as SVG/HTML."""

from pathlib import Path

W, H = 2600, 1800
OUT = Path(__file__).resolve().parent

MODULES = {
    "auth": ("Common / Authentication", "#FFF8E7", "#E8C547", "#D4AF37", 210, 150, 900, 115),
    "patient": ("Patient Module", "#EEF7EE", "#7CB87C", "#5A9A5A", 210, 290, 680, 560),
    "doctor": ("Doctor Module", "#F3ECFA", "#B48FD4", "#9678B8", 210, 880, 980, 300),
    "receptionist": ("Receptionist Module", "#FFF3E8", "#F0A868", "#D4894A", 1620, 290, 460, 500),
    "admin": ("Administrator Module", "#E8F2FC", "#6FA8E8", "#4A88C8", 1620, 880, 460, 340),
}

# Unique use cases: name -> (cx, cy, module_key)
USE_CASES = {
    # Auth
    "Register Account": (380, 215, "auth"),
    "Login": (580, 215, "auth"),
    "Logout": (780, 215, "auth"),
    "Reset Password": (980, 215, "auth"),
    # Patient
    "Search Doctors": (300, 360, "patient"),
    "View Doctor Profile": (520, 360, "patient"),
    "Book Appointment": (760, 360, "patient"),
    "Cancel Appointment": (300, 450, "patient"),
    "Reschedule Appt.": (520, 450, "patient"),
    "Consultation Chat": (760, 450, "patient"),
    "View Medical Record": (300, 540, "patient"),
    "Update Medical History": (520, 540, "patient"),
    "Rate Doctor": (760, 540, "patient"),
    "Add to Favorites": (300, 630, "patient"),
    "Report Doctor": (520, 630, "patient"),
    "Make Payment": (760, 630, "patient"),
    "View Notifications": (520, 720, "patient"),
    # Doctor
    "Manage Profile": (340, 960, "doctor"),
    "Manage Availability": (580, 960, "doctor"),
    "Manage Time Slots (Dr)": (820, 960, "doctor"),
    "View Appointments (Dr)": (1060, 960, "doctor"),
    "Manage Encounters": (340, 1050, "doctor"),
    "View Medical Records": (580, 1050, "doctor"),
    "Consultation Chat (Dr)": (820, 1050, "doctor"),
    "Appt. Exceptions": (1060, 1050, "doctor"),
    # Receptionist — shared names mapped to receptionist positions
    "Book Appt. for Patient": (1720, 360, "receptionist"),
    "Manage Visit Status": (1960, 360, "receptionist"),
    "View Appointments (Rec)": (1960, 450, "receptionist"),
    "Manage Time Slots (Rec)": (1720, 540, "receptionist"),
    "Register Patient": (1960, 540, "receptionist"),
    "Reschedule Affected Appts.": (1720, 630, "receptionist"),
    # Admin
    "Manage Doctors": (1720, 960, "admin"),
    "Verify Doctor": (1960, 960, "admin"),
    "Manage Clinics": (1720, 1050, "admin"),
    "Handle Reports": (1960, 1050, "admin"),
    "View Analytics": (1840, 1140, "admin"),
}

# Map PDF labels to internal keys for relationships
ALIAS = {
    "Manage Time Slots": "Manage Time Slots (Dr)",
    "View Appointments": "View Appointments (Dr)",
    "Consultation Chat": "Consultation Chat",
}

ACTOR_CASES = {
    "Patient": [
        "Register Account", "Login", "Logout", "Reset Password",
        "Search Doctors", "View Doctor Profile", "Book Appointment",
        "Cancel Appointment", "Reschedule Appt.", "Consultation Chat",
        "View Medical Record", "Update Medical History", "Rate Doctor",
        "Add to Favorites", "Report Doctor", "Make Payment", "View Notifications",
    ],
    "Doctor": [
        "Login", "Logout", "Reset Password",
        "Manage Profile", "Manage Availability", "Manage Time Slots (Dr)",
        "View Appointments (Dr)", "Manage Encounters", "View Medical Records",
        "Consultation Chat (Dr)", "Appt. Exceptions",
    ],
    "Receptionist": [
        "Login", "Logout", "Reset Password",
        "Book Appt. for Patient", "Manage Visit Status", "View Appointments (Rec)",
        "Manage Time Slots (Rec)", "Register Patient", "Reschedule Affected Appts.",
    ],
    "Administrator": [
        "Login", "Logout", "Reset Password",
        "Manage Doctors", "Verify Doctor", "Manage Clinics",
        "Handle Reports", "View Analytics",
    ],
}

ACTORS = [
    ("Patient", 60, 560, "#2E7D32"),
    ("Doctor", 60, 1020, "#6A1B9A"),
    ("Receptionist", 2460, 560, "#E65100"),
    ("Administrator", 2460, 1020, "#1565C0"),
]

# Reconstructed from pages 2–5
RELATIONSHIPS = [
    ("Search Doctors", "View Doctor Profile", "extend", "Patient selects result"),
    ("Search Doctors", "Book Appointment", "extend", "Patient chooses to book"),
    ("View Doctor Profile", "Book Appointment", "extend", "Patient chooses to book"),
    ("View Doctor Profile", "Add to Favorites", "extend", "Optional action"),
    ("View Doctor Profile", "Report Doctor", "extend", "Optional action"),
    ("Book Appointment", "Make Payment", "include", "Payment required"),
    ("Cancel Appointment", "Reschedule Appt.", "extend", "Patient reschedules"),
    ("Consultation Chat", "View Medical Record", "include", "Chat accesses record"),
    ("Consultation Chat", "Rate Doctor", "extend", "After consultation"),
    ("Manage Availability", "Manage Time Slots (Dr)", "extend", "Doctor sets slots"),
    ("Manage Encounters", "View Medical Records", "include", "Notes need record"),
    ("Consultation Chat (Dr)", "View Medical Records", "include", "Chat needs record"),
    ("Appt. Exceptions", "Manage Availability", "extend", "Doctor sets exception"),
    ("Appt. Exceptions", "Reschedule Affected Appts.", "include", "Updates affected appts."),
    ("Reset Password", "Login", "include", "Login after reset"),
    ("Register Patient", "Register Account", "include", "Creates account"),
    ("Book Appt. for Patient", "View Appointments (Rec)", "include", "Reflected in view"),
    ("Reschedule Affected Appts.", "Book Appointment", "include", "Creates new booking"),
    ("Manage Doctors", "Verify Doctor", "include", "Part of management"),
]

UC_FILL = {
    "auth": "#FFFDF5",
    "patient": "#F8FBF8",
    "doctor": "#FBF8FD",
    "receptionist": "#FFFAF5",
    "admin": "#F5FAFF",
}

DISPLAY = {
    "Manage Time Slots (Dr)": "Manage Time Slots",
    "View Appointments (Dr)": "View Appointments",
    "View Appointments (Rec)": "View Appointments",
    "Manage Time Slots (Rec)": "Manage Time Slots",
    "Consultation Chat (Dr)": "Consultation Chat",
}


def pos(name: str) -> tuple[float, float]:
    key = ALIAS.get(name, name)
    return USE_CASES[key][:2]


def module_box(title, bg, header, border, x, y, w, h) -> str:
    return "\n".join([
        f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="14" fill="{bg}" stroke="{border}" stroke-width="2"/>',
        f'<rect x="{x}" y="{y}" width="{w}" height="44" rx="14" fill="{header}"/>',
        f'<rect x="{x}" y="{y + 30}" width="{w}" height="14" fill="{header}"/>',
        f'<text x="{x + w/2}" y="{y + 30}" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" '
        f'font-size="18" font-weight="700" fill="#1a1a1a">{title}</text>',
    ])


def ellipse(name: str) -> str:
    cx, cy, mod = USE_CASES[name]
    label = DISPLAY.get(name, name)
    w = max(145, len(label) * 7.0)
    fill = UC_FILL[mod]
    return (
        f'<ellipse cx="{cx}" cy="{cy}" rx="{w/2}" ry="17" fill="{fill}" stroke="#333" stroke-width="1.5"/>'
        f'<text x="{cx}" y="{cy + 5}" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" '
        f'font-size="12.5" fill="#111">{label}</text>'
    )


def rel_line(fr: str, to: str, rel: str, label: str) -> str:
    x1, y1 = pos(fr)
    x2, y2 = pos(to)
    mx, my = (x1 + x2) / 2, (y1 + y2) / 2 - 16
    dash = "6,4" if rel == "include" else "none"
    st = "<<Include>>" if rel == "include" else "<<Extend>>"
    return (
        f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="#555" stroke-width="1.2" '
        f'stroke-dasharray="{dash}" marker-end="url(#arr)"/>'
        f'<text x="{mx}" y="{my}" text-anchor="middle" font-size="10.5" fill="#555" '
        f'font-family="Segoe UI, Arial, sans-serif">{st}</text>'
        f'<text x="{mx}" y="{my + 13}" text-anchor="middle" font-size="9.5" fill="#777" '
        f'font-family="Segoe UI, Arial, sans-serif">{label}</text>'
    )


def actor_line(actor_x: float, actor_y: float, case: str, right_side: bool) -> str:
    cx, cy = pos(case)
    if right_side:
        x1, x2 = actor_x - 10, cx + 95
    else:
        x1, x2 = actor_x + 130, cx - 95
    return f'<line x1="{x1}" y1="{actor_y}" x2="{x2}" y2="{cy}" stroke="#888" stroke-width="1" marker-end="url(#arrActor)"/>'


def build_svg() -> str:
    lines = [
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">',
        "<defs>",
        '<marker id="arr" markerWidth="7" markerHeight="7" refX="6" refY="3.5" orient="auto"><path d="M0,0 L7,3.5 L0,7 Z" fill="#555"/></marker>',
        '<marker id="arrActor" markerWidth="7" markerHeight="7" refX="6" refY="3.5" orient="auto"><path d="M0,0 L7,3.5 L0,7 Z" fill="#888"/></marker>',
        "</defs>",
        f'<rect width="{W}" height="{H}" fill="#FAFAFA"/>',
        f'<text x="{W/2}" y="55" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="36" font-weight="700">Virtual Medical Complex — Use Case Diagram</text>',
        f'<text x="{W/2}" y="92" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="21" fill="#555">System Overview · All Actors &amp; Use Cases</text>',
        f'<text x="{W - 50}" y="40" text-anchor="end" font-size="14" fill="#888" font-family="Segoe UI, Arial, sans-serif">Virtual Medical Complex Platform | UML Use Case Diagram · Page 1 of 5</text>',
        f'<rect x="170" y="125" width="2260" height="1480" rx="18" fill="none" stroke="#333" stroke-width="2.5" stroke-dasharray="12,6"/>',
        f'<text x="{W/2}" y="1595" text-anchor="middle" font-size="16" font-weight="600" font-family="Segoe UI, Arial, sans-serif" fill="#333">«System Boundary: Virtual Medical Complex Platform»</text>',
    ]

    for _, (title, bg, header, border, x, y, w, h) in MODULES.items():
        lines.append(module_box(title, bg, header, border, x, y, w, h))

    for name in USE_CASES:
        lines.append(ellipse(name))

    for rel in RELATIONSHIPS:
        lines.append(rel_line(*rel))

    for name, x, y, color in ACTORS:
        right = x > W / 2
        lines.extend([
            f'<rect x="{x - (130 if not right else 0)}" y="{y - 28}" width="130" height="56" rx="8" fill="{color}" opacity="0.12" stroke="{color}" stroke-width="2"/>',
            f'<text x="{x - 65 if not right else x - 65}" y="{y + 5}" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="17" font-weight="700" fill="{color}">{name}</text>',
        ])
        ax = x if not right else x - 130
        for case in ACTOR_CASES[name]:
            key = ALIAS.get(case, case)
            if key in USE_CASES:
                lines.append(actor_line(ax, y, case, right))

    lines.append("</svg>")
    return "\n".join(lines)


def build_html(svg: str) -> str:
    return f"""<!DOCTYPE html>
<html><head><meta charset="UTF-8"/>
<title>VMC Use Case Diagram Page 1</title>
<style>
@page {{ size: 420mm 297mm; margin: 8mm; }}
html, body {{ margin:0; padding:0; }}
.page {{ width:{W}px; height:{H}px; }}
</style></head>
<body><div class="page">{svg}</div></body></html>"""


def main() -> None:
    svg = build_svg()
    svg_path = OUT / "VMC_UseCaseDiagram_Page1.svg"
    html_path = OUT / "VMC_UseCaseDiagram_Page1.html"
    svg_path.write_text(svg, encoding="utf-8")
    html_path.write_text(build_html(svg), encoding="utf-8")
    print(svg_path)
    print(html_path)


if __name__ == "__main__":
    main()
