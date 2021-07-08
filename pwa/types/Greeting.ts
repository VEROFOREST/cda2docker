export class Greeting {
  public "@id"?: string;

  constructor(_id?: string, public name?: string) {
    this["@id"] = _id;
  }
}
