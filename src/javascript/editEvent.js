/*
 * Edit Event AJAX Script
 *
 * CHANGED: replacement event images are uploaded directly to Vercel Blob.
 * If no new file is selected, PHP keeps the event's existing image URL.
 */

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("editEventForm");
  const message = document.getElementById("editEventMessage");

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
    submitButton.innerHTML = '<i class="fa-solid fa-spinner"></i> Updating...';

    try {
      // CHANGED: the PHP update receives fields + optional Blob URL, not a file.
      const formData = new FormData(form);
      const imageFile = imageInput?.files?.[0] ?? null;

      // ADDED: remove the binary image so the Function request remains small.
      formData.delete("image");

      if (imageFile) {
        showMessage("Uploading replacement image...", "success");

        // ADDED: direct browser -> Blob upload.
        const blob = await window.EventImageBlobUpload.upload(imageFile);
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
        throw new Error(data.message || "Unable to update event.");
      }

      showMessage(data.message + " Redirecting...", "success");

      setTimeout(() => {
        window.location.href = data.redirect || "/admin";
      }, 1000);
    } catch (error) {
      console.error(error);
      showMessage(
        error instanceof Error ? error.message : "Unable to update event.",
        "error",
      );
    } finally {
      submitButton.disabled = false;
      submitButton.innerHTML = originalButtonText;
    }
  });
});
