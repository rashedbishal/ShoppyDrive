/**
 * The range App\Rules\StorableDateTime accepts for dates kept in MySQL
 * TIMESTAMP columns (flash sales, coupons, blog posts), as `datetime-local`
 * min/max values, so the picker itself refuses a year like 9999.
 */
export const STORABLE_DATETIME_MIN = '2000-01-01T00:00';
export const STORABLE_DATETIME_MAX = '2037-12-31T23:59';
