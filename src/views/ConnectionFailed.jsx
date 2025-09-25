import React from 'react';
import { reconnectSite } from '../helpers/auth';

const ConnectionFailed = () => {
  const handleConnectAgain = () => {
    // Trigger reconnection flow
    reconnectSite();
  };

  return (
    <div className="flex flex-col items-center justify-center min-h-96 p-10 text-center font-sans">
      <div className="text-5xl mb-6 text-red-500">
        ☹️
      </div>
      
      <h2 className="text-3xl font-semibold text-gray-800 mb-3 m-0">
        Connection Failed...
      </h2>
      
      <p className="text-base text-gray-500 mb-8 m-0 max-w-sm leading-relaxed">
        We couldn't connect your site. Please try again.
      </p>
      
      <button
        onClick={handleConnectAgain}
        className="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-lg border-none text-base font-medium cursor-pointer flex items-center gap-2 transition-colors duration-200"
      >
        Connect Again
        <span className="text-sm">→</span>
      </button>
    </div>
  );
};

export default ConnectionFailed;