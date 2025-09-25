import React from "react";
import { Button } from "@bsf/force-ui";
import { __ } from "@wordpress/i18n";

const UnverifiedState = () => {
  const handleGoToDashboard = () => {
    // Dummy function for now
    console.log("Go to Dashboard clicked");
  };

  return (
    <div className="flex justify-center items-start min-h-screen">
      <div className="bg-white shadow-md rounded-2xl p-8 max-w-lg w-full text-center">
        {/* Icon */}
        <div className="text-3xl mb-3">⚠️</div>

        <div className="flex flex-col items-center justify-center">
          {/* Title */}
          <h2 className="text-xl font-semibold text-gray-800 mb-2">
            {__("Verification Required", "surefeedback")}
          </h2>
          <p className="text-gray-500 mb-6 text-sm w-80 text-center">
            {__(
              "Your site needs to be verified before you can access the connection settings. Please complete the verification process first.",
              "surefeedback"
            )}
          </p>
        </div>

        {/* Buttons */}
        <div className="flex justify-center gap-4">
          <Button 
            variant="primary"
            onClick={handleGoToDashboard}
          >
            {__("Go to Dashboard", "surefeedback")}
          </Button>
          <Button 
            variant="secondary"
            onClick={handleGoToDashboard}
          >
            {__("Go to Dashboard", "surefeedback")}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default UnverifiedState;
