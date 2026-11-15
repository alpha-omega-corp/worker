// The admin's script: drag and drop for the lists an owner orders, images drawn without leaving the page, and motion.
// It is the base package's (alpha-omega-corp/site), booted with this site's GSAP so the package carries no dependency
// of its own. Found by a glob rather than imported, so a site whose installed base predates the script still builds:
// it then boots nothing, and the admin works without it as it always has.
import { gsap } from 'gsap';

const admin = Object.values(import.meta.glob('../../vendor/alpha-omega-corp/site/resources/js/admin.js', { eager: true }))[0];

admin?.boot({ gsap });
