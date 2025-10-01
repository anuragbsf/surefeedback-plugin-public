import React from "react";
import { XCircle } from "lucide-react";
import { Button } from "@bsf/force-ui";
import { __ } from "@wordpress/i18n";
import { authenticateRedirect } from "../helpers/auth";

const NotConnected = ({ setIsStarted }) => {
  return (
    <div className="flex justify-center items-center min-h-screen bg-gray-50">
      <div className="bg-white shadow-md rounded-2xl p-8 text-center max-w-md w-full">
        <XCircle className="mx-auto text-red-500 w-12 h-12 mb-4" />
        <h2 className="text-xl font-semibold text-gray-900 mb-2">
          {__("SureFeedback Not Connected!", "text-domain")}
        </h2>
        <p className="text-gray-500 mb-6">
          {__(
            'Click "Connect Website" to authorize this website with SureFeedback.',
            "text-domain"
          )}
        </p>
        <Button 
          variant="primary"
          size="lg"
          onClick={() =>  authenticateRedirect()}
          className="px-8 w-full"
        >
          {__("Connect Website", "text-domain")}
        </Button>
      </div>
    </div>
  );
};

export default NotConnected;
