---
name: Publishly Clear
colors:
  surface: '#fcf8ff'
  surface-dim: '#dcd8e5'
  surface-bright: '#fcf8ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f5f2ff'
  surface-container: '#f0ecf9'
  surface-container-high: '#eae6f4'
  surface-container-highest: '#e4e1ee'
  on-surface: '#1b1b24'
  on-surface-variant: '#464555'
  inverse-surface: '#302f39'
  inverse-on-surface: '#f3effc'
  outline: '#777587'
  outline-variant: '#c7c4d8'
  surface-tint: '#4d44e3'
  primary: '#3525cd'
  on-primary: '#ffffff'
  primary-container: '#4f46e5'
  on-primary-container: '#dad7ff'
  inverse-primary: '#c3c0ff'
  secondary: '#00687a'
  on-secondary: '#ffffff'
  secondary-container: '#57dffe'
  on-secondary-container: '#006172'
  tertiary: '#7e3000'
  on-tertiary: '#ffffff'
  tertiary-container: '#a44100'
  on-tertiary-container: '#ffd2be'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#e2dfff'
  primary-fixed-dim: '#c3c0ff'
  on-primary-fixed: '#0f0069'
  on-primary-fixed-variant: '#3323cc'
  secondary-fixed: '#acedff'
  secondary-fixed-dim: '#4cd7f6'
  on-secondary-fixed: '#001f26'
  on-secondary-fixed-variant: '#004e5c'
  tertiary-fixed: '#ffdbcc'
  tertiary-fixed-dim: '#ffb695'
  on-tertiary-fixed: '#351000'
  on-tertiary-fixed-variant: '#7b2f00'
  background: '#fcf8ff'
  on-background: '#1b1b24'
  surface-variant: '#e4e1ee'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.02em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  2xl: 48px
  3xl: 64px
  container-max: 1280px
  gutter: 24px
  margin-mobile: 16px
---

## Brand & Style
The design system is built on the principles of clarity, reliability, and modern efficiency. It targets a professional audience that values productivity and straightforward navigation over visual excess. The style is categorized as **Corporate / Modern**, prioritizing high legibility and a logical hierarchy that feels "MVP-ready"—polished enough for a final product but structured for rapid iteration.

The emotional response should be one of quiet confidence. By utilizing a familiar Indigo-centered palette, the UI establishes immediate trust, while the clean whitespace and systematic layout reduce cognitive load for users performing complex tasks.

## Colors
This design system utilizes a palette designed for high functional contrast and accessibility. 

- **Primary & Secondary:** Indigo serves as the dominant brand driver for primary actions, while Cyan is used for secondary interactive elements or data visualization highlights.
- **Feedback:** Success, Error, and Accent colors follow industry standards to ensure intuitive recognition of system states.
- **Neutrals:** The background and surface colors use a cool-gray tint to prevent eye strain. Borders are kept soft to define structure without creating visual noise.

## Typography
The design system relies exclusively on **Inter**, a typeface designed for screen legibility. The type scale is conservative to ensure it fits comfortably in data-heavy interfaces.

- **Headlines:** Use Bold (700) or Semi-Bold (600) weights with slight negative letter-spacing on larger sizes to maintain a compact, "designed" feel.
- **Body:** Set at 16px for standard reading, using the Regular (400) weight. 
- **Labels:** Used for buttons, tags, and navigation items. These utilize Medium (500) or Semi-Bold (600) weights to differentiate them from static content.

## Layout & Spacing
The layout follows a **Fixed Grid** model for desktop and a **Fluid Grid** for mobile devices.

- **Desktop:** 12-column grid with a 1280px maximum container width. Gutters are fixed at 24px.
- **Tablet:** 8-column fluid grid with 24px margins.
- **Mobile:** 4-column fluid grid with 16px margins.

Spacing follows an 8px (0.5rem) linear scale. All components and layout containers should use these increments to maintain a consistent vertical and horizontal rhythm. Avoid using "pixel-perfect" custom values; always snap to the nearest spacing token.

## Elevation & Depth
Depth in the design system is achieved through **Tonal Layers** and **Ambient Shadows**.

- **Level 0 (Flat):** Used for background surfaces and structural regions.
- **Level 1 (Low):** Subtle borders (#E2E8F0) separate sections of content on the same plane.
- **Level 2 (Raised):** Cards and secondary containers use a soft, diffused shadow (Box Shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1)) to indicate interactability.
- **Level 3 (Overlay):** Modals and dropdowns use a more pronounced shadow with a wider blur and a slight Indigo tint (rgb(79 70 229 / 0.08)) to pull the element significantly forward from the surface.

## Shapes
The shape language is consistently **Rounded**. By setting the base radius to 8px (0.5rem), the UI feels approachable and modern without being overly playful or geometric.

- **Standard Radius:** 8px (Buttons, Input Fields, Small Cards).
- **Large Radius:** 16px (Feature Cards, Modals).
- **Extra Large Radius:** 24px (Hero containers).
- **Pill:** Reserved exclusively for tags, badges, and search bars to differentiate them from primary action buttons.

## Components
- **Buttons:** Primary buttons use the Indigo background with white text. Secondary buttons use a transparent background with an Indigo border.
- **Input Fields:** 8px corner radius with a 1px border (#E2E8F0). Focus states should use a 2px Indigo border with a subtle Indigo glow.
- **Chips & Badges:** Low-contrast backgrounds (using Muted #F1F5F9 or a light tint of the status color) with high-contrast text. Use pill-shaped rounding.
- **Cards:** White surface, 8px or 16px corner radius, and a Level 2 shadow. Avoid borders on cards unless they are displayed on a white background.
- **Lists:** Clean rows with 1px bottom borders (#E2E8F0). Hover states should use the Muted background color (#F1F5F9) to indicate interactivity.
- **Progress Bars:** Use the Secondary Cyan color for completion to distinguish it from primary Indigo actions.