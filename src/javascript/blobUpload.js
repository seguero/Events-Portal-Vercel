/*
 * Vercel Blob Direct Upload Helper
 *
 * Uploads event images directly from the browser to Vercel Blob so the image
 * never passes through the PHP/Vercel Function request-body limit.
 */

(() => {
  // ADDED: keep browser-side validation aligned with the PHP application.
  const MAX_IMAGE_SIZE = 2 * 1024 * 1024;
  const ALLOWED_IMAGE_TYPES = new Set([
    "image/jpeg",
    "image/png",
    "image/webp",
  ]);

  // ADDED: current Vercel Blob API version used by the official SDK.
  const BLOB_API_VERSION = "12";

  /**
   * Validate the selected event image before requesting an upload token.
   */
  function validateFile(file) {
    if (!file) {
      throw new Error("Please choose an image file.");
    }

    if (!ALLOWED_IMAGE_TYPES.has(file.type)) {
      throw new Error("Please upload a valid image file: JPG, PNG or WEBP.");
    }

    if (file.size > MAX_IMAGE_SIZE) {
      throw new Error("Image must be smaller than 2MB.");
    }
  }

  /**
   * Ask PHP for a short-lived token scoped to one image upload.
   */
  async function requestUploadToken(file) {
    const response = await fetch("/admin/blobToken", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
      body: JSON.stringify({
        // ADDED: PHP uses the MIME type to choose the extension and token rules.
        contentType: file.type,
      }),
    });

    const data = await response.json().catch(() => null);

    if (!response.ok || !data?.success) {
      throw new Error(
        data?.message || "Image upload could not be prepared.",
      );
    }

    return data;
  }

  /**
   * Upload an image directly to Vercel Blob.
   */
  async function upload(file) {
    validateFile(file);

    const token = await requestUploadToken(file);

    // ADDED: upload goes browser -> Vercel Blob, bypassing /admin/store.
    const uploadUrl = new URL("https://vercel.com/api/blob/");
    uploadUrl.searchParams.set("pathname", token.pathname);

    const requestId = `${token.storeId}:${Date.now()}:${
      globalThis.crypto?.randomUUID?.() || Math.random().toString(16).slice(2)
    }`;

    const response = await fetch(uploadUrl.toString(), {
      method: "PUT",
      headers: {
        // ADDED: this is a short-lived client token, never the read/write token.
        Authorization: `Bearer ${token.clientToken}`,
        "x-api-blob-request-id": requestId,
        "x-vercel-blob-store-id": token.storeId,
        "x-api-blob-request-attempt": "0",
        "x-api-version": BLOB_API_VERSION,
        "x-vercel-blob-access": "public",
        "x-content-type": file.type,
      },
      body: file,
    });

    const responseText = await response.text();
    let data = null;

    try {
      data = JSON.parse(responseText);
    } catch (error) {
      console.error("Invalid Vercel Blob response:", responseText);
    }

    if (!response.ok || !data?.url) {
      const apiMessage =
        data?.error?.message ||
        data?.message ||
        "Image upload to Vercel Blob failed.";

      throw new Error(apiMessage);
    }

    return data;
  }

  // ADDED: expose one small helper for createEvent.js and editEvent.js.
  window.EventImageBlobUpload = {
    upload,
  };
})();
