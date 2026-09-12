![ZeroDrop Screenshot](assets/zerodrop-preview.png)

**🌐 English** · [🇮🇹 Italiano](README.it.md)

**ZeroDrop** is an open-source file transfer tool built for maximum privacy and
security. Created by [TivuStream](https://tivustream.com), it follows a "Privacy
First" philosophy, using end-to-end encryption (E2EE) directly in the user's
browser.

## 🌟 Key features

-   **E2EE encryption (AES-256-GCM)**: files are encrypted locally before being sent. The key never leaves the user's device.
-   **Zero-knowledge**: the server only ever receives encrypted blobs, and knows nothing about the content, name or type of the file.
-   **Self-destruct**: files are deleted from the server immediately after the first download, or after 24 hours of inactivity.
-   **Privacy-focused**: no IP address logging, no tracking cookies, no sign-up required.
-   **Rate limiting**: built-in abuse protection (max 5 uploads per 10 minutes per user, based on an anonymised IP hash).
-   **Bilingual interface (IT/EN)**: language switch built into the interface, with the preference saved locally.
-   **Modern interface**: a clean, intuitive "Dark Glass" design, optimised for desktop and mobile.
-   **Compatibility**: runs on any hosting with PHP support, shared hosting included.

## 🛠️ How it works (for the technically minded)

1.  **Key generation**: the browser generates a random 256-bit AES-GCM key through the `Web Crypto API`.
2.  **Encryption**: the file is encrypted locally with a 12-byte IV (initialization vector).
3.  **Fragment identifier**: the key is appended to the link after the `#` symbol. Since the fragment identifier is never sent to the server, the key stays a secret between sender and recipient.
4.  **Ephemerality**: the PHP backend handles incoming blobs and takes care of automatically cleaning up expired files.

## 🚀 Quick install

1.  Clone the repository onto your server, or download the files.
2.  Create an `uploads/` folder in the project root.
3.  Make sure the `uploads/` folder is writable (e.g. `chmod 755` or `777`).
4.  (Optional) Upload your own logo as `logo-tivustream.png`.
5.  Point your web server at the project folder.

## 🌍 Language (IT/EN)

-   You can switch language from the interface, using the button in the top right corner.
-   You can also force a language through the URL by adding `?lang=en` or `?lang=it`.

## 🔐 Recommended security setup (Cloudflare)

If you use Cloudflare, we recommend that you:
-   Set the **Security Level** to "Automated" or "Medium".
-   Create a **Cache Rule** to bypass the cache on the `uploads/` folder.
-   Note that the maximum upload size on free plans is 100 MB.

## 📄 License

This project is released under the **MIT** license. See the `LICENSE` file for details.

## 🤝 Contributing

Pull requests and suggestions are welcome. If you find a bug or have an idea for
a new feature, open an issue or send a PR.

---
A project by [TivuStream](https://tivustream.com) - *Privacy First System*
