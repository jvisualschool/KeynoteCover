# KeynoteCover

[한국어](README.md) | English

A PHP web app that turns a PDF or a short description into a **text-free, 16:9 3D voxel cover**. Add your title directly in Keynote.

- A preview at the top, with input and appearance settings in two columns below. The layout switches to a single column on mobile.
- Upload a PDF with 1–10 pages, up to 25 MB, or enter a description of up to 2,000 characters.
- Choose scene complexity, image coverage, title space, a color theme, background color, and transparent PNG output.
- Download a 2560×1440 PNG. The app checks the content, background, and visible text, although AI checks cannot guarantee that every issue is detected.
- **Each user supplies their own OpenAI API key. No shared key is provided.**

## Screenshots

### Cover generator

Enter a PDF or description and adjust the cover settings below the preview. Choose image coverage, title space, one of six color themes, and a background.

![Cover generator](docs/images/cover-generator.png)

### Cover gallery

Search published covers, view their details and generation prompts, or download PNG files.

![Cover gallery](docs/images/cover-gallery.png)

These screenshots illustrate the interface. The distribution in this repository uses individual API key settings instead of a login, and publishing to the gallery is optional.

## API keys and visibility

Enter your key and click **키 설정** (Set key). This checks the format only; the key and model permissions are checked on the first OpenAI request. The key is sent to the app server over HTTPS and stored encrypted in the server-side session. It is not stored in browser storage, job state, generation prompts, the gallery, or logs. It expires after a maximum of eight hours. Click **키 삭제** (Remove key) to prevent its use for new requests. Removing a key does not stop work already running.

The hosting server decrypts the key to process requests. Install the app on a server you operate or trust. Encryption does not prevent the server operator from accessing credentials.

Results are not published to the public gallery by default. If you select the option to publish to the gallery, the **cover, analysis, filename, additional requests, and generation prompt** become public. The original PDF and API key are not published. In the same browser session used to publish a cover, you can hide it with **공개 취소** (Unpublish) in the details view and undo that action.

PDFs and job data older than seven days are deleted when the cleanup task runs. Published covers are retained separately. If the session cookie is lost, access to earlier jobs and permission to unpublish their covers are also lost.

## Installation

A PHP server is required. **GitHub Pages cannot run PHP or background jobs.**

Requirements: PHP 8.3 or later, cURL, GD, mbstring, Sodium, Poppler (`pdfinfo` and `pdftoppm`), and permission to run PHP CLI processes. No external JavaScript libraries or Composer packages are required.

Ubuntu example:

```sh
sudo apt-get update
sudo apt-get install apache2 libapache2-mod-php php-cli php-curl php-gd php-mbstring poppler-utils
git clone https://github.com/jvisualschool/KeynoteCover.git
cd KeynoteCover
```

1. Set the web server's DocumentRoot to `public/`. Do not expose the entire repository through the web server.
2. Configure HTTPS, then set `KC_ORIGIN` and `KC_DATA_DIR` using `config/apache.conf.example` as a reference. `KC_ORIGIN` must be the exact origin without a path, such as `https://cover.example.com`.
3. Create a private data directory and grant read/write access only to the PHP execution account. Do not grant that account write access to application source files.

```sh
sudo install -d -o www-data -g www-data -m 700 /srv/keynotecover-data
sudo a2enmod headers
```

`.env.example` lists the environment variables. PHP does not load this file automatically; configure the variables in the web server or process environment. Change models with `KC_TEXT_MODEL` and `KC_IMAGE_MODEL`. Your API project must have access to the selected models.

Schedule the cleanup task to run as the PHP execution account. It must use the same data directory as the web server.

```sh
KC_DATA_DIR=/srv/keynotecover-data /usr/bin/php /srv/keynotecover/app/cleanup.php
```

For a local preview only (HTTP is allowed only for loopback origins):

```sh
KC_ORIGIN=http://127.0.0.1:8080 KC_PHP_BIN="$(command -v php)" php -S 127.0.0.1:8080 -t public
```

Use Apache and HTTPS in production. PHP's built-in development server does not apply the static-file security headers defined in `.htaccess`.

## Costs and retention

OpenAI API charges apply to PDF or description analysis, scene brief updates, image generation, and review. Depending on the review results, a single generation request can produce up to two image-generation calls. API errors and connection failures are not automatically retried. The app allows two concurrent jobs across the installation and limits request frequency and daily request counts.

The server uses the key for [Bearer authentication](https://developers.openai.com/api/reference/overview); the browser does not send it directly to OpenAI. See the [official image API documentation](https://developers.openai.com/api/reference/resources/images/methods/edit) for supported output specifications.

## Checks

```sh
bash scripts/check.sh
```

Checks use a temporary local server, synthetic keys, and synthetic data. They do not call OpenAI. GitHub Actions runs the same checks and scans for secrets with Gitleaks. See [SECURITY.md](SECURITY.md) for the security scope and limitations.
