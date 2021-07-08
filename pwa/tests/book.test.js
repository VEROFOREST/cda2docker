import { render, screen } from "@testing-library/react";
import App from "../pages/books/index";

describe("App", () => {
    it("renders without crashing", () => {
        render( < App / > );
        expect(
            screen.getByRole("heading", { name: "Book List" })
        ).toBeInTheDocument();
    });
});