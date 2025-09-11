import React from "react";
import { Button } from "@bsf/force-ui"; // keeping your button style system
import { __ } from "@wordpress/i18n"; // for translations if needed
import { CheckCircle } from "lucide-react"; // using an icon for status

const Connected = () => {
  return (
    <div className="flex justify-center items-start min-h-screen">
      <div className="bg-white shadow-md rounded-2xl p-8 max-w-lg w-full text-center">
        {/* Emoji / Confetti */}
        <div className="text-3xl mb-3">🎉</div>

        <div className="flex flex-col items-center justify-center">
          {/* Title */}
          <h2 className="text-xl font-semibold text-gray-800 mb-2">
            {__("Website Connected Successfully!", "surefeedback")}
          </h2>
          <p className="text-gray-500 mb-6 text-sm w-80 text-center flex items-center">
            {__(
              "Your site is now linked with SureFeedback. Start gathering client feedback without friction.",
              "surefeedback"
            )}
          </p>
        </div>

        {/* Info Box */}
        <div className="border rounded-lg mb-6 px-4 py-3 flex items-center justify-center">
          <div className="grid grid-cols-2 gap-y-3 text-left">
            <span className="font-medium">Connection Site:</span>
            <span className="text-gray-700">https://example.com</span>

            <span className="font-medium">Organization:</span>
            <span className="text-gray-700">Example Inc.</span>

            <span className="font-medium">Status:</span>
            <span className="flex items-center gap-1 text-green-600 bg-green-100 px-2 py-0.5 rounded-full text-xs font-medium w-fit">
              <CheckCircle size={14} />
              Active
            </span>
          </div>
        </div>

        {/* Buttons */}
        <div className="flex justify-center gap-4">
          <Button variant="secondary">{__("Reconnect", "surefeedback")}</Button>
          <Button
            variant="destructive"
            className="!bg-red-50 !text-red-600 !border !border-red-300 hover:!bg-red-100"
          >
            {__("Disconnect", "surefeedback")}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default Connected;
