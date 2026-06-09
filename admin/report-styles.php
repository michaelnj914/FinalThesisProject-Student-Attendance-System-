<style>

  /* ─────────────────────────────────────────────
     HERO CARD
     The full-width gradient banner at the top of
     the report showing student info + rate circle
  ───────────────────────────────────────────── */
  .report-hero {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-d) 100%); /* Diagonal gradient from the site's primary blue to a darker shade */
    border-radius: var(--radius);   /* Rounded corners using the global CSS variable */
    padding: 1.75rem 2rem;          /* 1.75rem top/bottom, 2rem left/right inner spacing */
    color: #fff;                    /* All text inside the hero is white */
    display: flex;                  /* Arrange children (avatar · info · rate circle) in a row */
    align-items: center;            /* Vertically center all children in the row */
    gap: 1.5rem;                    /* Spacing between each child element */
    margin-bottom: 1.5rem;          /* Space below the hero before the next section */
    flex-wrap: wrap;                /* Let children wrap to a new line on narrow screens */
  }

  /* ─────────────────────────────────────────────
     AVATAR CIRCLE
     Shows the uppercase first initial of the
     student's first name (e.g. "J")
  ───────────────────────────────────────────── */
  .report-avatar {
    width: 80px; height: 80px;              /* Fixed 80×80 px square — made circular below */
    background: rgba(255,255,255,.2);       /* Translucent white fill; lets the gradient show through */
    border-radius: 50%;                     /* Turns the square into a perfect circle */
    display: flex; align-items: center; justify-content: center; /* Center the letter both horizontally and vertically */
    font-size: 2.2rem; font-weight: 700;    /* Large bold letter */
    flex-shrink: 0;                         /* Prevents the circle from shrinking when space is limited */
    border: 3px solid rgba(255,255,255,.4); /* Semi-transparent white ring around the circle */
  }

  /* ─────────────────────────────────────────────
     HERO INFO BLOCK
     Text area in the middle: name, student no.,
     program, section, and standing badge
  ───────────────────────────────────────────── */
  .report-hero-info { flex: 1; min-width: 200px; } /* Grows to fill remaining space; won't collapse below 200px */

  .report-hero h2 { font-size: 1.4rem; margin-bottom: .3rem; } /* Student full name; slightly larger than body, small gap below */

  .report-hero p {
    font-size: .85rem;    /* Slightly smaller than normal body text */
    opacity: .88;         /* Slightly faded so the name heading is the visual focus */
    margin: .2rem 0;      /* Minimal vertical gap between each detail row */
    display: flex;        /* Place the icon and text side by side */
    align-items: center;  /* Vertically align the icon with the text */
    gap: .4rem;           /* Small gap between the icon and the label text */
  }

  /* ─────────────────────────────────────────────
     RATE CIRCLE
     The large circular percentage display on the
     far right of the hero (e.g. "87.5% ATTENDANCE")
  ───────────────────────────────────────────── */
  .rate-circle {
    width: 120px; height: 120px;  /* Fixed 120×120 px square — made circular below */
    border-radius: 50%;           /* Turns the square into a perfect circle */
    display: flex; flex-direction: column; /* Stack the % number above the "ATTENDANCE" label */
    align-items: center; justify-content: center; /* Center both items inside the circle */
    font-weight: 800;             /* Extra-bold text for the large number */
    margin-left: auto;            /* Pushes the circle to the far right of the flex row */
    border: 6px solid rgba(255,255,255,.35);  /* Thick semi-transparent white ring */
    background: rgba(255,255,255,.12);        /* Very faint white fill; gradient still visible through it */
    flex-shrink: 0;               /* Prevents the circle from shrinking when space is tight */
  }

  .rate-circle .rate-num { font-size: 1.9rem; line-height: 1; } /* The big % number; line-height:1 removes extra whitespace below it */

  .rate-circle .rate-lbl {
    font-size: .65rem;          /* Very small label below the number */
    opacity: .8;                /* Slightly faded so it doesn't compete with the big number */
    text-transform: uppercase;  /* "Attendance" renders as "ATTENDANCE" */
    letter-spacing: .06em;      /* Spread letters apart slightly for a clean all-caps look */
    margin-top: .2rem;          /* Tiny gap between the number and this label */
  }

  /* ─────────────────────────────────────────────
     STANDING BADGE
     Pill-shaped label below the student's name
     showing "Good Standing", "At Risk", or "Dropped"
  ───────────────────────────────────────────── */
  .standing-badge {
    display: inline-flex;   /* Shrink-wraps to content; places icon and text side by side */
    align-items: center;    /* Vertically align the icon and text */
    gap: .4rem;             /* Gap between icon and text */
    padding: .3rem .9rem;   /* Vertical and horizontal inner spacing */
    border-radius: 50px;    /* Fully rounded pill shape */
    font-size: .78rem;      /* Slightly smaller than body text */
    font-weight: 700;       /* Bold label */
    background: rgba(255,255,255,.2); /* Default translucent white (overridden by modifier classes below) */
    color: #fff;            /* White text on all badge variants */
    margin-top: .5rem;      /* Space above the badge so it sits below the detail rows */
  }

  .standing-good { background: rgba(45,198,83,.35);  } /* Green tint  — attendance rate >= 80% */
  .standing-risk { background: rgba(244,162,97,.35); } /* Orange tint — attendance rate 60–79% */
  .standing-drop { background: rgba(230,57,70,.35);  } /* Red tint    — attendance rate below 60% */

  /* ─────────────────────────────────────────────
     SUMMARY STAT ROW & BOXES
     Four white cards in a row: Total Days,
     Present, Absent, and Attendance Rate
  ───────────────────────────────────────────── */
  .summary-row {
    display: flex;        /* Place boxes side by side */
    gap: 1rem;            /* Gap between each box */
    flex-wrap: wrap;      /* Wrap to a new line on small screens */
    margin-bottom: 1.5rem;/* Space below the row before the next section */
  }

  .summary-box {
    flex: 1;                      /* Each box grows equally to share the available width */
    min-width: 130px;             /* Minimum width before the box wraps to a new line */
    background: var(--white);     /* White card background */
    border-radius: var(--radius); /* Rounded corners */
    box-shadow: var(--shadow);    /* Subtle drop shadow to lift the card off the page */
    padding: 1.25rem 1.25rem;     /* Equal padding on all sides */
    text-align: center;           /* Center the number and label inside the box */
  }

  .summary-box .s-num {
    font-size: 2.2rem;    /* Large stat number (e.g. "24") */
    font-weight: 800;     /* Extra bold */
    line-height: 1;       /* Removes extra line-height gap underneath the number */
    margin-bottom: .3rem; /* Small gap between the number and its label */
  }

  .summary-box .s-lbl {
    font-size: .72rem;          /* Small label text (e.g. "TOTAL SCHOOL DAYS") */
    color: var(--gray);         /* Muted grey so the label doesn't compete with the big number */
    text-transform: uppercase;  /* All-caps label */
    font-weight: 600;           /* Semi-bold */
    letter-spacing: .04em;      /* Slight letter spacing for a clean all-caps look */
  }

  /* ─────────────────────────────────────────────
     MONTHLY BREAKDOWN BARS
     One progress bar per calendar month showing
     the present/absent ratio for that month
  ───────────────────────────────────────────── */
  .month-bar-wrap { margin-bottom: .9rem; } /* Vertical gap between each month's bar group */

  .month-label {
    display: flex;                  /* Place children in a row */
    justify-content: space-between; /* Month name on the left, stats on the right */
    font-size: .8rem;               /* Slightly smaller than body text */
    margin-bottom: .3rem;           /* Gap between the label row and the bar below */
  }

  .bar-track {
    background: #eef0f8; /* Light grey-blue background for the unfilled part of the bar */
    border-radius: 50px; /* Fully rounded ends — pill shape */
    height: 11px;        /* Fixed bar height */
    overflow: hidden;    /* Clips the fill so it never visually overflows the track */
  }

  .bar-fill {
    height: 100%;              /* Fill the full height of the track */
    border-radius: 50px;       /* Rounded right end to match the track */
    transition: width .5s ease;/* Smoothly animate the bar width over 0.5 seconds on page load */
    /* width and background-color are set inline by PHP, calculated per month */
  }

  /* ─────────────────────────────────────────────
     PRINT STYLES
     Applied when the user prints the page or
     saves it as a PDF via the browser
  ───────────────────────────────────────────── */
  @media print {
    .sidebar, .topbar, .no-print { display: none !important; } /* Hide the sidebar, top bar, and filter form — not needed on paper */
    .main-content { margin-left: 0 !important; }               /* Remove the left offset that normally makes room for the sidebar */
    .page-content { padding: 0 !important; }                   /* Remove extra padding so content fills the printed page edge-to-edge */

    /* Browsers suppress background colors/gradients by default when printing to save ink.   */
    /* These two rules force the browser to print them exactly as they appear on screen.     */
    .report-hero { -webkit-print-color-adjust: exact; print-color-adjust: exact; } /* Preserve the hero gradient on print */
    .bar-fill    { -webkit-print-color-adjust: exact; print-color-adjust: exact; } /* Preserve the coloured progress bars on print */
  }

</style>