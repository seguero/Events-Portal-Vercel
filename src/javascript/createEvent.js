/*
 * Create Event AJAX Script
 *
 * CHANGED: event images are uploaded directly from the browser to Vercel Blob.
 * Only the returned image URL is sent to the PHP event-save endpoint.
 */

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("createEventForm");
  const message = document.getElementById("createEventMessage");

  if (!form || !message) {
    return;
  }

  function clearMessage() {
    message.textContent = "";
    message.classList.remove("success", "error");
  }

  function showMessage(text, type) {
    message.textContent = text;
    message.classList.remove("success", "error");
    message.classList.add(type);
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearMessage();

    const submitButton = form.querySelector('button[type="submit"]');
    const imageInput = form.querySelector('input[name="image"]');
    const originalButtonText = submitButton.innerHTML;

    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fa-solid fa-spinner"></i> Saving...';

    try {
      // CHANGED: build FormData once, then remove the binary file before PHP.
      const formData = new FormData(form);
      const imageFile = imageInput?.files?.[0] ?? null;

      // ADDED: never send the file itself to /admin/store.
      formData.delete("image");

      if (imageFile) {
        showMessage("Uploading image...", "success");

        // ADDED: direct browser -> Blob upload avoids Vercel's 413 limit.
        const blob = await window.EventImageBlobUpload.upload(imageFile);

        // ADDED: PHP receives only the small permanent Blob URL.
        formData.set("image_url", blob.url);
      }

      const response = await fetch(form.action, {
        method: "POST",
        body: formData,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
      });

      const text = await response.text();
      let data;

      try {
        data = JSON.parse(text);
      } catch (error) {
        console.error("Invalid server response:", text);
        throw new Error("Server returned an invalid response.");
      }

      if (!response.ok || !data.success) {
        throw new Error(data.message || "Unable to save event.");
      }

      showMessage(data.message + " Redirecting...", "success");

      setTimeout(() => {
        window.location.href = data.redirect || "/admin";
      }, 1000);
    } catch (error) {
      console.error(error);
      showMessage(
        error instanceof Error ? error.message : "Unable to save event.",
        "error",
      );
    } finally {
      // ADDED: always restore the button when a request fails.
      submitButton.disabled = false;
      submitButton.innerHTML = originalButtonText;
    }
  });
});
