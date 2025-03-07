import json

# ===============================
# Shortcut: Hazard Assessment Processor
# ===============================
# This shortcut performs the following steps:
# 1. Dictates voice input.
# 2. Creates a JSON payload (inserting the dictated text) for the ChatGPT API.
# 3. Sends a POST request to the GPT-4o endpoint.
# 4. Parses the JSON response.
# 5. Displays the structured output.
# 6. Appends the structured output to an Apple Note (e.g., for hazard assessments).

hazard_assessment_shortcut = {
    "WFWorkflowName": "Hazard Assessment Processor",
    "WFWorkflowActions": [
        # 1. Dictate Text Action
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.dictate.text",
            "WFWorkflowActionParameters": {
                "WFDictateTextActionPrompt": "Please dictate your hazard assessment."
            }
        },
        # 2. Text Action to create JSON payload.
        # The placeholder [[Input]] should be replaced by the output of the Dictate Text action (Magic Variable).
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.text",
            "WFWorkflowActionParameters": {
                "WFTextActionText": (
                    '{\n'
                    '  "model": "gpt-4o",\n'
                    '  "prompt": "Convert the following unstructured hazard assessment into structured data with sections for hazards, equipment, and special notes:\\n\\n[[Input]]",\n'
                    '  "temperature": 0.5,\n'
                    '  "max_tokens": 300\n'
                    '}'
                )
            }
        },
        # 3. Get Contents of URL Action: sends the JSON payload to the ChatGPT API.
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.getcontentsofurl",
            "WFWorkflowActionParameters": {
                "WFGetContentsOfURLActionURL": "https://api.openai.com/v1/engines/gpt-4o/completions",
                "WFGetContentsOfURLActionMethod": "POST",
                "WFGetContentsOfURLActionRequestBody": {
                    "WFSerializationType": "WFTextTokenString",
                    "Value": "Provided by previous Text Action"
                },
                "WFGetContentsOfURLActionHeaders": {
                    "Content-Type": "application/json",
                    "Authorization": "Bearer YOUR_API_KEY_HERE"  # <-- Replace with your API key
                }
            }
        },
        # 4. Get Dictionary Value Action (optional): extracts the field containing the structured output.
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.dictionary.getvalue",
            "WFWorkflowActionParameters": {
                "WFDictionaryGetValueKey": "choices"  # Adjust the key if your API response differs.
            }
        },
        # 5. Show Result Action: displays the returned structured data.
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.showresult",
            "WFWorkflowActionParameters": {
                "WFShowResultActionText": "The structured output from GPT-4o:"
            }
        },
        # 6. Append to Note Action: logs the output in an Apple Note named "Hazard Assessments".
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.appendtonote",
            "WFWorkflowActionParameters": {
                "WFNoteName": "Hazard Assessments",
                "WFNoteContent": "Structured output from GPT-4o"  # Replace with the magic variable from the API response.
            }
        }
    ]
}

# Write the Hazard Assessment shortcut to a file.
with open("HazardAssessmentProcessor.shortcut", "w") as f:
    json.dump(hazard_assessment_shortcut, f, indent=2)


# ===============================
# Shortcut: Equipment Inspection Tracker
# ===============================
# This shortcut follows a similar process:
# 1. Dictates equipment inspection details.
# 2. Creates a JSON payload for the API (with appropriate prompt).
# 3. Calls GPT-4o to structure the maintenance log.
# 4. Shows and appends the result to an Apple Note for equipment inspections.

equipment_inspection_shortcut = {
    "WFWorkflowName": "Equipment Inspection Tracker",
    "WFWorkflowActions": [
        # Dictate Text Action for equipment inspection details.
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.dictate.text",
            "WFWorkflowActionParameters": {
                "WFDictateTextActionPrompt": "Please dictate your equipment inspection details."
            }
        },
        # Text Action to create JSON payload for equipment inspection.
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.text",
            "WFWorkflowActionParameters": {
                "WFTextActionText": (
                    '{\n'
                    '  "model": "gpt-4o",\n'
                    '  "prompt": "Convert the following equipment inspection log into a structured maintenance record:\\n\\n[[Input]]",\n'
                    '  "temperature": 0.5,\n'
                    '  "max_tokens": 300\n'
                    '}'
                )
            }
        },
        # API call to GPT-4o.
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.getcontentsofurl",
            "WFWorkflowActionParameters": {
                "WFGetContentsOfURLActionURL": "https://api.openai.com/v1/engines/gpt-4o/completions",
                "WFGetContentsOfURLActionMethod": "POST",
                "WFGetContentsOfURLActionRequestBody": {
                    "WFSerializationType": "WFTextTokenString",
                    "Value": "Provided by previous Text Action"
                },
                "WFGetContentsOfURLActionHeaders": {
                    "Content-Type": "application/json",
                    "Authorization": "Bearer YOUR_API_KEY_HERE"  # <-- Replace with your API key
                }
            }
        },
        # Show the result.
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.showresult",
            "WFWorkflowActionParameters": {
                "WFShowResultActionText": "The structured output from GPT-4o:"
            }
        },
        # Append to Note Action: logs to a note named "Equipment Inspections".
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.appendtonote",
            "WFWorkflowActionParameters": {
                "WFNoteName": "Equipment Inspections",
                "WFNoteContent": "Structured output from GPT-4o"
            }
        }
    ]
}

# Write the Equipment Inspection shortcut to a file.
with open("EquipmentInspectionTracker.shortcut", "w") as f:
    json.dump(equipment_inspection_shortcut, f, indent=2)


# ===============================
# Shortcut: Mileage Tracker
# ===============================
# This shortcut captures mileage details (via voice or other means),
# creates a JSON payload to structure the data,
# calls GPT-4o for processing, and then displays and logs the result.

mileage_tracker_shortcut = {
    "WFWorkflowName": "Mileage Tracker",
    "WFWorkflowActions": [
        # Dictate Text Action for mileage details.
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.dictate.text",
            "WFWorkflowActionParameters": {
                "WFDictateTextActionPrompt": "Please dictate your mileage details."
            }
        },
        # Text Action to create JSON payload for mileage logs.
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.text",
            "WFWorkflowActionParameters": {
                "WFTextActionText": (
                    '{\n'
                    '  "model": "gpt-4o",\n'
                    '  "prompt": "Convert the following mileage log into structured data with timestamps, locations, and distance traveled:\\n\\n[[Input]]",\n'
                    '  "temperature": 0.5,\n'
                    '  "max_tokens": 300\n'
                    '}'
                )
            }
        },
        # API call to GPT-4o.
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.getcontentsofurl",
            "WFWorkflowActionParameters": {
                "WFGetContentsOfURLActionURL": "https://api.openai.com/v1/engines/gpt-4o/completions",
                "WFGetContentsOfURLActionMethod": "POST",
                "WFGetContentsOfURLActionRequestBody": {
                    "WFSerializationType": "WFTextTokenString",
                    "Value": "Provided by previous Text Action"
                },
                "WFGetContentsOfURLActionHeaders": {
                    "Content-Type": "application/json",
                    "Authorization": "Bearer YOUR_API_KEY_HERE"  # <-- Replace with your API key
                }
            }
        },
        # Show the result.
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.showresult",
            "WFWorkflowActionParameters": {
                "WFShowResultActionText": "The structured output from GPT-4o:"
            }
        },
        # Append to Note Action: logs to a note named "Mileage Logs".
        {
            "WFWorkflowActionIdentifier": "is.workflow.actions.appendtonote",
            "WFWorkflowActionParameters": {
                "WFNoteName": "Mileage Logs",
                "WFNoteContent": "Structured output from GPT-4o"
            }
        }
    ]
}

# Write the Mileage Tracker shortcut to a file.
with open("MileageTracker.shortcut", "w") as f:
    json.dump(mileage_tracker_shortcut, f, indent=2)


# ===============================
# Note Templates File
# ===============================
# This text file contains templates for various logs.
# You can copy these templates into your Apple Notes for consistent record keeping.

note_templates = {
    "HazardAssessmentTemplate": (
        "Hazard Assessment Log\n"
        "---------------------\n"
        "Date: [[Date]]\n"
        "Time: [[Time]]\n"
        "Location: [[Location]]\n"
        "\n"
        "Hazards Identified:\n"
        "- [[Hazard 1]]\n"
        "- [[Hazard 2]]\n"
        "\n"
        "Required Equipment:\n"
        "- [[Equipment 1]]\n"
        "- [[Equipment 2]]\n"
        "\n"
        "Special Considerations:\n"
        "[[Notes]]\n"
    ),
    "EquipmentInspectionTemplate": (
        "Equipment Inspection Log\n"
        "------------------------\n"
        "Date: [[Date]]\n"
        "Equipment: [[Equipment Name]]\n"
        "\n"
        "Inspection Details:\n"
        "- [[Issue 1]]\n"
        "- [[Issue 2]]\n"
        "\n"
        "Maintenance Actions:\n"
        "[[Actions Taken]]\n"
    ),
    "MileageLogTemplate": (
        "Mileage Log\n"
        "-----------\n"
        "Date: [[Date]]\n"
        "Start Location: [[Start Location]]\n"
        "End Location: [[End Location]]\n"
        "Distance Traveled: [[Distance]]\n"
        "Fuel Used: [[Fuel]]\n"
        "Time Spent: [[Time]]\n"
    )
}

# Write the templates to a text file.
with open("NoteTemplates.txt", "w") as f:
    for template_name, template_content in note_templates.items():
        f.write(f"== {template_name} ==\n{template_content}\n\n")

print("Shortcut files and note templates have been generated. Please add your API keys where indicated.") 