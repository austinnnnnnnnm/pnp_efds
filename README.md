# PNP EFDS Prototype

This repository includes a static, browser-based prototype that can be hosted with GitHub Pages.

## Demo sign-in

- Email: `demo@pnp.gov.ph`
- Password: `password123`

You can also create a prototype account from the sign-in page. Accounts and document changes are saved in the current browser's `localStorage`; they are not shared with other users or devices.

## GitHub Pages

In the repository, open **Settings > Pages**, choose **Deploy from a branch**, select the branch containing these files and the root (`/`) folder, then save. The site entry point is `index.html`.

The prototype runs entirely in the browser and stores demo accounts and documents in `localStorage`. GitHub Pages does not execute PHP, and browser-stored demo data is not suitable for real credentials or production records.
