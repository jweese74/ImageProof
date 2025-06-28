<?php
// index.php

require_once 'config.php';
require_once 'functions.php'; // Configuration & helper functions
require_once 'process.php';   // POST form-processing logic
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Infinite Muse Toolkit</title>
    <style>
        /* General Styles */
        body {
            background-color: #000;
            color: #ccc;
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        h1 {
            color: #d2b648; /* Old Gold */
            text-align: center;
        }

        .description {
            color: #bfa952; /* Darker Old Gold */
            font-style: italic;
            text-align: center;
            margin: 20px auto;
            max-width: 80%;
            line-height: 1.6;
        }

        h2, legend {
            color: #fff;
        }

        form {
            width: 80%;
            margin: 0 auto;
            border: 1px solid #444;
            padding: 20px;
            border-radius: 8px;
            background-color: #111;
        }

        fieldset {
            margin-bottom: 20px;
            border: 1px solid #444;
            padding: 15px;
            border-radius: 6px;
            background-color: #222;
        }

        label {
            display: block;
            margin: 12px 0 4px;
            text-align: center; /* Center the labels */
            position: relative;
        }

        .required {
            color: red;
            margin-left: 4px;
        }

        input[type="text"],
        input[type="date"],
        textarea,
        select {
            width: 90%;
            max-width: 90%;
            margin: 0 auto;
            display: block;
            padding: 8px;
            border: 1px solid #555;
            border-radius: 4px;
            background-color: #222;
            color: #eee;
            transition: border-color 0.3s ease, background-color 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="date"]:focus,
        textarea:focus,
        select:focus {
            border-color: #888;
            background-color: #333;
            outline: none;
        }

        textarea {
            height: 120px; /* Increased height for description */
            resize: vertical;
        }

        input[type="file"] {
            margin: 10px auto;
            display: block;
            color: #eee;
        }

        .preview {
            display: block;
            margin: 10px auto;
            max-width: 150px;
            height: auto;
        }

        .button-group {
            text-align: center;
            margin-top: 30px;
        }

        button {
            padding: 10px 20px;
            background-color: #444;
            color: #fff;
            border: 1px solid #666;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease, outline 0.3s ease;
            margin: 0 10px;
            font-size: 1em;
        }

        button:hover {
            background-color: #666;
        }

        button:focus {
            outline: 2px solid #888;
            outline-offset: 2px;
        }

        p.notice,
        p.fine-print {
            font-size: 0.85em;
            text-align: center;
            color: #aaa;
            margin-top: 25px;
        }

        p.fine-print {
            color: #ccc;
        }

        .highlight {
            color: #d2b648; /* Old Gold */
            font-style: italic;
            text-align: center;
            margin-top: 10px;
        }

        /* Header Image */
        .header-image {
            display: block;
            margin: 0 auto 20px auto;
            max-width: 400px; /* Adjusted to align with "Infinite Muse Toolbox" size */
            height: auto;
        }

        /* Important Notice */
        .important {
            text-align: center;
            color: #d2b648; /* Old Gold */
            font-weight: bold;
            margin-bottom: 10px;
        }

        /* Tooltip Styles */
        .tooltip-container {
            position: relative;
            display: inline-block;
            cursor: pointer;
            margin-left: 5px; /* Space between label text and icon */
        }

        .tooltip-text {
            visibility: hidden;
            width: 220px; /* Adjust as needed */
            background-color: #333;
            color: #fff;
            text-align: left;
            border-radius: 4px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%; /* Position above the icon */
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.85em;
            line-height: 1.4;
        }

        .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%; /* At the bottom of the tooltip */
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }

        .tooltip-container:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Link Styles */
        a {
            color: #d2b648; /* Old Gold */
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
        }

        /* FAQ Link Centering */
        .faq-link {
            display: block;
            margin: 1rem auto;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header>
        <img src="./watermarks/muse_signature_black.png" alt="Muse Signature" class="header-image">
    </header>

    <h1>Infinite Muse Toolkit</h1>

    <a href="faq.html" target="_blank" title="Frequently Asked Questions" class="faq-link">Frequently Asked Questions</a>

    <p class="description">
        Welcome to your ultimate tool for digital art management and protection! This platform is designed specifically for digital artists to simplify the process of preparing, enhancing, and securing their artwork for the digital world. Whether you're sharing your creations online, selling them to collectors, or archiving them for personal use, this tool ensures your artwork is always accompanied by professional-grade metadata, watermarked for protection, and packaged with a digital certificate of authenticity. With just a few clicks, you can embed essential details like your name, copyright notice, and creative intent directly into your artwork, establishing a robust digital fingerprint that proves your ownership and creative vision.<br><br>
        Here, your privacy and security are top priorities. All uploaded files and generated assets are cleared daily at midnight (Eastern Standard Time), ensuring that your data is never tracked or stored long-term. This guarantees that your creations remain solely yours, with no risk of unauthorized access or persistent records. By combining efficiency, security, and authenticity, this platform empowers artists to confidently share their work while preserving their creative rights and enhancing their professional image. Start transforming your digital art into a protected and verifiable masterpiece today.
    </p>

    <form method="post" enctype="multipart/form-data">
        <!-- Basic Information Fieldset -->
        <fieldset>
            <legend>Basic Information</legend>

            <!-- Title of the Artwork -->
            <label>
                Title of the Artwork <span class="required">*</span>
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Enter the unique title of the artwork. Keep it descriptive yet concise. Think of a name that is memorable, aligns with the artwork's story, and is easily associated with the piece. This will be the primary name displayed alongside your artwork.
                    </span>
                </span>
            </label>
            <input type="text" name="title" required placeholder="e.g. Starry Night" />

            <!-- Description of the Artwork -->
            <label>
                Description of the Artwork
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Describe the theme or concept of your artwork and what story or message it conveys. Highlight key elements like colours, shapes, textures, or emotions, and mention anything unique about the piece. Think of it as introducing your art to someone seeing it for the first time.
                    </span>
                </span>
            </label>
            <textarea name="description" placeholder="Describe your artwork here..."></textarea>

            <!-- SEO Headline -->
            <label>
                SEO Headline
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        The SEO Headline is the title that appears in search engine results. Keep it concise and include important keywords that describe your artwork to help people find it online. Aim for a maximum of 60 characters.
                    </span>
                </span>
            </label>
            <input type="text" name="seo_headline" placeholder="e.g. Vibrant Sunset Over Mountains" />

            <!-- Creation Date -->
            <label>
                Creation Date (YYYY-MM-DD) <span class="required">*</span>
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Enter the date the artwork was completed.
                    </span>
                </span>
            </label>
            <input type="date" name="creation_date" required />
        </fieldset>

        <!-- Keywords & Genre Fieldset -->
        <fieldset>
            <legend>Keywords & Genre</legend>

            <!-- Keywords -->
            <label>
                Keywords (comma-separated)
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Enter keywords that best describe your artwork, separated by commas. Focus on simple, specific terms that highlight the subject, style, or mood of the piece (e.g., 'cat, flowers, vibrant'). These keywords help others find your artwork when searching.
                    </span>
                </span>
            </label>
            <input type="text" name="keywords" placeholder="e.g. cat, flowers, vibrant" />

            <!-- Intellectual Genre(s) -->
            <label>
                Intellectual Genre(s)
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Specify the intellectual genre(s) of your artwork, such as 'Fine Art,' 'Still Life,' or 'Abstract.' These categories reflect the broader artistic style or tradition your piece belongs to, helping to classify and contextualize your work.
                    </span>
                </span>
            </label>
            <input type="text" name="genre" placeholder="e.g. Fine Art, Still Life" />
        </fieldset>

        <!-- Ownership & Metadata Fields Fieldset -->
        <fieldset>
            <legend>Ownership & Metadata Fields</legend>

            <!-- By-line Name -->
            <label>
                By-line Name
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Enter the name or entity associated with the artwork's creation, such as your personal name, pseudonym, or brand (e.g., 'Jane Doe' or 'Infinite Muse Art'). This will be displayed as the creator's credit alongside the artwork.
                    </span>
                </span>
            </label>
            <input type="text" name="byline_name" placeholder="e.g. Jane Doe | Infinite Muse Art" />

            <!-- Copyright Notice -->
            <label>
                Copyright Notice
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Enter the copyright notice for your artwork, including the copyright symbol, year, and owner (e.g., '© 2025 Infinite Muse Arts. All rights reserved.'). This protects your legal rights and specifies ownership.
                    </span>
                </span>
            </label>
            <input type="text" name="copyright_notice" placeholder="e.g. © 2025 Infinite Muse Arts. All rights reserved." />

            <!-- License Type -->
            <label>
                License Type <span class="required">*</span>
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Select the appropriate license for your artwork. Hover over each option to learn more about the licenses.
                    </span>
                </span>
            </label>
            <select name="license_type" required>
                <option value="" disabled selected>Select a license</option>
                <option value="cc_by" title="Allows others to share and adapt the work, even commercially, as long as they give appropriate credit to the original creator.">
                    Creative Commons Attribution 4.0 International (CC BY 4.0)
                </option>
                <option value="cc_by_sa" title="Allows others to remix, adapt, and build upon your work even for commercial purposes, as long as they credit you and license their new creations under identical terms.">
                    Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0)
                </option>
                <option value="cc_by_nc" title="Allows others to remix, adapt, and build upon your work non-commercially, and although their new works must also acknowledge you and be non-commercial, they don’t have to license their derivative works on the same terms.">
                    Creative Commons Attribution-NonCommercial 4.0 International (CC BY-NC 4.0)
                </option>
                <option value="cc0" title="Waives all rights and dedicates the work to the public domain, allowing others to use the work without any restrictions.">
                    Creative Commons Zero (CC0 1.0 Universal)
                </option>
                <option value="royalty_free" title="Allows the purchaser to use the image without paying royalties each time it is used, typically for a one-time fee.">
                    Royalty-Free License
                </option>
                <option value="public_domain" title="Allows others to use the work without any restrictions.">
                    Public Domain Dedication
                </option>
                <option value="custom" title="If none of the standard licenses fit your needs, please ensure you modify your certificate after download.">
                    Custom License
                </option>
            </select>

            <!-- Creator -->
            <label>
                Creator
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Enter the name of the individual or entity who created the artwork (e.g., 'Jane Doe' or 'Infinite Muse Art'). Unlike the By-line, which is a public-facing credit, this field is for internal metadata purposes and may not always be displayed publicly.
                    </span>
                </span>
            </label>
            <input type="text" name="creator" placeholder="e.g. Jane Doe | Infinite Muse Art" />

            <!-- Position -->
            <label>
                Position
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Enter the professional role or position of the creator related to this artwork (e.g., 'Digital Artist,' 'Photographer,' or 'Illustrator'). This field adds context to the creator's contribution and is primarily for metadata purposes.
                    </span>
                </span>
            </label>
            <input type="text" name="position" placeholder="e.g. Digital Artist" />

            <!-- Copyright/Information URL -->
            <label>
                Copyright/Information URL
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Provide a URL linking to a copyright statement or additional information about the artwork (e.g., 'https://yoursite.com/copyright-notice'). This serves as a reference for legal rights or detailed context about the piece.
                    </span>
                </span>
            </label>
            <input type="text" name="webstatement" placeholder="e.g. https://yoursite.com/copyright-notice" />

            <!-- Watermark Overlay Text (Optional) -->
            <label>
                Watermark Overlay Text (Optional)
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Enter text to be used as a watermark in the preview output of the image (e.g., '© 2024 Infinite Muse Art | @infinitemuse.ai | https://infinitemusearts.com'). Five visible text watermarks will be randomly placed, and one hidden 'ghost watermark' will be embedded for added security against copying or theft. This field is optional.
                    </span>
                </span>
            </label>
            <input type="text" name="overlay_text" placeholder="e.g. © 2024 Infinite Muse Art | @infinitemuse.ai | https://infinitemusearts.com" />
        </fieldset>

        <!-- Image Watermark Fieldset -->
        <fieldset>
            <legend>
                Image Watermark (Optional)
                <span class="tooltip-container">
                    &#x2753; <!-- Unicode for Question Mark Emoji -->
                    <span class="tooltip-text">
                        Upload a custom watermark to be applied to the preview image. Watermarks with transparent backgrounds work best. The watermark will be resized and centred at the bottom of the image. For signed versions, the watermark will act as the signature and appear in reduced size. Accepted file formats: PNG, JPG, JPEG, and WEBP.
                    </span>
                </span>
            </legend>
            <p style="text-align:center;">Upload a custom watermark below:</p>
            <input type="file" name="watermark_upload" accept=".png,.jpg,.jpeg,.webp" />
            <img src="" alt="Watermark Preview" class="preview" id="watermarkPreview" style="display:none;">
            <script>
                const watermarkInput = document.querySelector('input[name="watermark_upload"]');
                const watermarkPreview = document.getElementById('watermarkPreview');

                watermarkInput.addEventListener('change', event => {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = e => {
                            watermarkPreview.src = e.target.result;
                            watermarkPreview.style.display = 'block'; // Show the preview
                        };
                        reader.readAsDataURL(file);
                    } else {
                        watermarkPreview.src = ''; // Clear the preview
                        watermarkPreview.style.display = 'none'; // Hide the preview
                    }
                });
            </script>
        </fieldset>

        <!-- Artwork Files to Process Fieldset -->
        <fieldset>
            <legend>Artwork Files to Process</legend>
            <p style="text-align:center;">Select the files to upload:</p>
            <input type="file" name="images[]" multiple required />
            <img alt="File Preview" class="preview" id="filePreview" style="display:none;">
            <p class="highlight">Maximum individual file size: 250 MB</p>
            <script>
                const fileInput = document.querySelector('input[name="images[]"]');
                const filePreview = document.getElementById('filePreview');

                fileInput.addEventListener('change', event => {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = e => {
                            filePreview.src = e.target.result;
                            filePreview.style.display = 'block'; // Show the preview
                        };
                        reader.readAsDataURL(file);
                    } else {
                        filePreview.src = ''; // Clear the preview
                        filePreview.style.display = 'none'; // Hide the preview
                    }
                });
            </script>
        </fieldset>

        <!-- Button Group -->
        <div class="button-group">
            <button type="submit" name="submit" value="1">Process</button>
            <button type="reset" id="clearButton">Clear</button>
        </div>
    </form>

    <!-- Notices -->
    <p class="notice">
        All uploaded files are cleared daily at midnight (EST) for security and storage management. Users will not receive a notification prior to deletion, so ensure to download necessary files beforehand.
    </p>
    <p class="fine-print">
        &copy; 2025 Infinite Muse Arts
        <br>
        <img src="./watermarks/muse_signature_black.png" alt="Infinite Muse Logo" class="header-image" style="max-width: 200px;">
    </p>
</body>
</html>
