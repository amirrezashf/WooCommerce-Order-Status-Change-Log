# WooCommerce Order Status Change Log

Logs WooCommerce order status changes and displays recent activity in the WordPress admin panel.

## Description

WooCommerce Order Status Change Log records every WooCommerce order status transition.

Each log includes:

- Order ID
- Previous status
- New status
- Change date and time
- User or process responsible for the change

A dedicated report page is added under the WooCommerce admin menu.

## Features

- Logs WooCommerce order status changes
- Stores previous and new statuses
- Records the responsible user or system process
- Supports REST API, webhook, cron, and system changes
- Displays recent logs in the WordPress admin panel
- Filter reports by time period
- Paginated report table
- Direct order and user links
- Compatible with WooCommerce HPOS
- Automatic cleanup of old logs
- Uses a dedicated database table
- Single-file plugin

## Requirements

- PHP 7.4+
- WordPress 6.0+
- WooCommerce

## Installation

1. Download the repository as a ZIP file.
2. Open **Plugins → Add New Plugin → Upload Plugin**.
3. Select the downloaded ZIP file.
4. Install and activate the plugin.

## Usage

After activation, open:

```text
WooCommerce → Order Status Logs
```

The report can display status changes from:

- Last 24 hours
- Last 72 hours
- Last 7 days
- Last 30 days

By default, 50 log records are displayed per page.

## Data Storage

The plugin creates the following custom database table:

```text
{prefix}wc_order_status_logs
```

The table stores:

- Log ID
- Order ID
- Previous order status
- New order status
- Change time in UTC
- User ID
- User display name
- Change source type

## Automatic Cleanup

Logs older than `180 days` are deleted automatically by a daily WordPress cron event.

The retention period can be changed using the `wc_oscl_retention_days` filter.

Return `0` to disable automatic cleanup.

```php
add_filter(
	'wc_oscl_retention_days',
	static function () {
		return 365;
	}
);
```

## Filters

### `wc_oscl_required_capability`

Changes the capability required to view the report.

```php
add_filter(
	'wc_oscl_required_capability',
	static function () {
		return 'manage_options';
	}
);
```

### `wc_oscl_items_per_page`

Changes the number of logs displayed per page.

```php
add_filter(
	'wc_oscl_items_per_page',
	static function () {
		return 100;
	}
);
```

### `wc_oscl_retention_days`

Changes log retention duration.

```php
add_filter(
	'wc_oscl_retention_days',
	static function () {
		return 90;
	}
);
```

### `wc_oscl_log_data`

Modifies log data before database insertion.

```php
add_filter(
	'wc_oscl_log_data',
	static function ( $data, $order, $old_status, $new_status ) {
		return $data;
	},
	10,
	4
);
```

## HPOS Compatibility

The plugin declares compatibility with WooCommerce High-Performance Order Storage.

Order edit links are generated using WooCommerce utilities whenever available.

## License

GPL-3.0

## Author

**Amirreza Shayesteh Far**

- Website: https://amirrezaa.ir/
- GitHub: https://github.com/amirrezashf
- Repository: https://github.com/amirrezashf/WooCommerce-Order-Status-Change-Log

---

# گزارش تغییر وضعیت سفارش‌های ووکامرس

ثبت تغییرات وضعیت سفارش‌های ووکامرس و نمایش آخرین فعالیت‌ها در پنل مدیریت وردپرس.

## توضیحات

افزونه WooCommerce Order Status Change Log تمام تغییرات وضعیت سفارش‌های ووکامرس را ثبت می‌کند.

هر لاگ شامل اطلاعات زیر است:

- شناسه سفارش
- وضعیت قبلی
- وضعیت جدید
- تاریخ و ساعت تغییر
- کاربر یا فرایند انجام‌دهنده تغییر

یک صفحه گزارش اختصاصی به منوی ووکامرس اضافه می‌شود.

## ویژگی‌ها

- ثبت تغییر وضعیت سفارش‌های ووکامرس
- ذخیره وضعیت قبلی و جدید
- ثبت کاربر یا فرایند عامل تغییر
- تشخیص تغییر توسط REST API، webhook، cron یا سیستم
- نمایش گزارش در پنل مدیریت
- فیلتر بر اساس بازه زمانی
- جدول صفحه‌بندی‌شده
- لینک مستقیم سفارش و کاربر
- سازگار با WooCommerce HPOS
- حذف خودکار لاگ‌های قدیمی
- استفاده از جدول اختصاصی دیتابیس
- معماری تک‌فایلی

## نیازمندی‌ها

- PHP 7.4+
- WordPress 6.0+
- WooCommerce

## نصب

1. repository را به‌صورت ZIP دانلود کنید.
2. در وردپرس وارد **افزونه‌ها ← افزودن افزونه تازه ← بارگذاری افزونه** شوید.
3. فایل ZIP را انتخاب کنید.
4. افزونه را نصب و فعال کنید.

## نحوه استفاده

پس از فعال‌سازی، وارد مسیر زیر شوید:

```text
ووکامرس ← لاگ وضعیت سفارش‌ها
```

گزارش را می‌توان برای بازه‌های زیر مشاهده کرد:

- ۲۴ ساعت اخیر
- ۷۲ ساعت اخیر
- ۷ روز اخیر
- ۳۰ روز اخیر

به‌صورت پیش‌فرض، در هر صفحه ۵۰ رکورد نمایش داده می‌شود.

## ذخیره‌سازی داده

افزونه جدول اختصاصی زیر را ایجاد می‌کند:

```text
{prefix}wc_order_status_logs
```

این جدول اطلاعات زیر را ذخیره می‌کند:

- شناسه لاگ
- شناسه سفارش
- وضعیت قبلی سفارش
- وضعیت جدید سفارش
- زمان تغییر به‌صورت UTC
- شناسه کاربر
- نام نمایشی کاربر
- نوع عامل تغییر

## پاک‌سازی خودکار

لاگ‌های قدیمی‌تر از `۱۸۰ روز` با یک cron روزانه به‌صورت خودکار حذف می‌شوند.

مدت نگهداری را می‌توان با filter زیر تغییر داد:

```php
add_filter(
	'wc_oscl_retention_days',
	static function () {
		return 365;
	}
);
```

برای غیرفعال کردن پاک‌سازی خودکار، مقدار `0` برگردانید.

## سازگاری با HPOS

افزونه سازگاری خود را با WooCommerce High-Performance Order Storage اعلام می‌کند.

برای ساخت لینک صفحه ویرایش سفارش، در صورت موجود بودن از utilityهای رسمی ووکامرس استفاده می‌شود.

## مجوز

GPL-3.0

## نویسنده

**Amirreza Shayesteh Far**

- وب‌سایت: https://amirrezaa.ir/
- GitHub: https://github.com/amirrezashf
- Repository: https://github.com/amirrezashf/WooCommerce-Order-Status-Change-Log
