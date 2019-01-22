# Marketo Fetch

This is a PHP library that interacts with the Marketo Asset API.

It is not fully-featured, and has only been designed for limited use cases.

## First-Time Setup

1. Copy the `.env.example` file to `.env`:
    ```
    cp .env.example .env
    ```
2. Update `.env` with your marketo API credentials.

## Commands

`fetch.php` outlines some example API use cases:

- Retrieve all variables currently assigned to landing pages.
    ```
    php fetch.php vars-assigned
    ```
- Retrieve all variables currently used within templates.
    ```
    php fetch.php vars-used
    ```
- Compare variables assigned to landing pages and used within templates.
    ```
    php fetch.php vars-compare
    ```
- Retrieve all landing pages.
    ```
    php fetch.php landing-pages
    ```
- Retrieve all landing page templates.
    ```
    php fetch.php landing-page-templates
    ```
- Purge all cached responses.
    ```
    php fetch.php purge-cache
    ```
