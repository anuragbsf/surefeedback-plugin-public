import React from "react";
import { ChevronRight } from "lucide-react";
import { Button } from "@bsf/force-ui";
import { __ } from "@wordpress/i18n";

const Welcome = () => {
  return (
    <div>
      {/* Top content */}
      <div className="flex flex-col items-center justify-center">
        <img
          src={`${sureFeedbackAdmin.surefeedback_icon}`}
          alt={__("Custom SVG", "surefeedback")}
          className="object-contain"
        />
        <h1 className="text-2xl font-semibold mt-4">
          Welcome to the Setup Wizard!
        </h1>
        <p
          className="text-center text-gray-600 mt-2"
          style={{ width: "460px" }}
        >
          SureFeedback makes it easy to collect and manage customer comments,
          helping you take action and improve satisfaction with less effort.
        </p>
        <Button
          icon={<ChevronRight />}
          type="submit"
          style={{
            backgroundColor: "#4353FF",
            marginTop: "14px",
            borderRadius: "6px",
          }}
          iconPosition="right"
          //   className="sticky"
        >
          {__("Get Started Now", "surefeedback")}
        </Button>
        <Button
          variant="link"
          //   icon={<ChevronRight />}
          type="submit"
          iconPosition="left"
          style={{ marginTop: "20px" }}
        >
          {__("Go Back to the dashboard", "surefeedback")}
        </Button>
      </div>
      {/* Middle Content */}
      <div className="mt-8">
        <div
          className="relative bg-cover bg-center bg-no-repeat"
          style={{
            backgroundImage: `url(${sureFeedbackAdmin.welcome_background})`,
            width: "730px",
            height: "437px",
          }}
        >
          <img
            src={`${sureFeedbackAdmin.welcome}`}
            alt={__("Welcome Image", "surefeedback")}
            className="absolute inset-0"
            style={{
              width: "623px",
              height: "437px",
            }}
          />
        </div>
      </div>
      {/* Before Footer content */}
      <div className="mt-5"></div>
    </div>
  );
};

export default Welcome;
