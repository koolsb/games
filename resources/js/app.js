/*
 * Single Vite entry for the whole site. Each game contributes its own
 * module; registering an Alpine component costs nothing on pages that
 * never instantiate it, so one bundle serves every route.
 */
import './qwixx/game';
